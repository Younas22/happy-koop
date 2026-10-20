<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Order;
use App\Models\PaymentEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsStoreFixtures;
use Tests\TestCase;

/**
 * Two classes of failure that are invisible until exploited: reading another
 * customer's records (IDOR), and a replayed payment webhook being applied
 * twice (api-contract.md 6, 7).
 */
class OwnershipAndWebhookTest extends TestCase
{
    use BuildsStoreFixtures, RefreshDatabase;

    /** Creates a paid-looking order owned by the given user. */
    private function orderFor(?int $userId, string $email): Order
    {
        return Order::query()->create([
            'order_number' => 'HK-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'access_token' => Str::random(48),
            'user_id' => $userId,
            'email' => $email,
            'currency' => 'USD',
            'subtotal_amount' => 5200,
            'discount_amount' => 0,
            'shipping_amount' => 599,
            'tax_amount' => null,
            'total_amount' => 5799,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'shipping_address' => ['line1' => '1 Example St', 'city' => 'Portland', 'countryCode' => 'US'],
            'billing_address' => ['line1' => '1 Example St', 'city' => 'Portland', 'countryCode' => 'US'],
        ]);
    }

    public function test_a_customer_cannot_read_another_customers_order(): void
    {
        $owner = $this->customerUser('owner@example.test');
        $intruder = $this->customerUser('intruder@example.test');

        $order = $this->orderFor($owner->id, 'owner@example.test');

        Sanctum::actingAs($intruder, ['customer']);

        // 404, not 403: existence itself is not disclosed.
        $this->getJson('/api/v1/account/orders/'.$order->order_number)
            ->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_the_owner_can_read_their_own_order(): void
    {
        $owner = $this->customerUser('owner2@example.test');
        $order = $this->orderFor($owner->id, 'owner2@example.test');

        Sanctum::actingAs($owner, ['customer']);

        $this->getJson('/api/v1/account/orders/'.$order->order_number)
            ->assertOk()
            ->assertJsonPath('data.orderNumber', $order->order_number);
    }

    public function test_a_guest_order_requires_the_correct_access_token(): void
    {
        $this->seedStore();
        $order = $this->orderFor(null, 'guest@example.test');

        // Wrong token.
        $this->getJson('/api/v1/orders/by-number/'.$order->order_number.'?token=wrong')
            ->assertStatus(404);

        // Correct token.
        $this->getJson('/api/v1/orders/by-number/'.$order->order_number.'?token='.$order->access_token)
            ->assertOk()
            ->assertJsonPath('data.paymentStatus', 'unpaid');
    }

    public function test_a_stripe_webhook_with_a_bad_signature_changes_nothing(): void
    {
        $this->seedStore();
        $this->enableStripe();

        $payload = json_encode(['id' => 'evt_bad_sig', 'type' => 'payment_intent.succeeded']);

        $this->call(
            'POST',
            '/api/v1/webhooks/stripe',
            [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't=1,v1=deadbeef'],
            $payload
        )->assertStatus(400);

        $this->assertSame(0, PaymentEvent::query()->count());
    }

    public function test_a_replayed_stripe_event_is_recorded_only_once(): void
    {
        $this->seedStore();
        $this->enableStripe();

        // The job would call Stripe's API; this test is about recording and
        // idempotency, so keep it off the network and deterministic.
        Queue::fake();

        $payload = json_encode([
            'id' => 'evt_replay_me',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_test_1', 'amount' => 5799, 'currency' => 'usd']],
        ], JSON_UNESCAPED_SLASHES);

        $send = fn () => $this->call(
            'POST',
            '/api/v1/webhooks/stripe',
            [], [], [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $this->stripeSignature($payload, 'whsec_fixtureWebhookSecret'),
            ],
            $payload
        );

        $send()->assertOk();
        $send()->assertOk()->assertJsonPath('data.duplicate', true);

        // The unique index on event_id is what makes processing idempotent.
        $this->assertSame(1, PaymentEvent::query()->where('event_id', 'evt_replay_me')->count());
    }

    /** Builds a header Stripe's verifier will accept for this exact body. */
    private function stripeSignature(string $payload, string $secret): string
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return 't='.$timestamp.',v1='.$signature;
    }
}
