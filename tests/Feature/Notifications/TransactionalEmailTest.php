<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Events\OrderPlaced;
use App\Mail\ContactInquiryAckMail;
use App\Mail\ContactInquiryAdminMail;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderDeliveredMail;
use App\Mail\OrderPlacedAdminMail;
use App\Models\ContactInquiry;
use App\Models\Order;
use App\Models\Product;
use App\Services\Payments\PaymentStatusMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsStoreFixtures;
use Tests\TestCase;

/**
 * The transactional email workflows: contact inquiries, order placement and
 * order delivery.
 *
 * What these tests are really protecting is the "exactly once" promise. A
 * customer who receives two order confirmations, or a delivery email for an
 * order that was merely created, loses confidence in the store - so every
 * workflow is asserted twice: once for the email it must send, and once for
 * the duplicate it must not.
 */
class TransactionalEmailTest extends TestCase
{
    use BuildsStoreFixtures, RefreshDatabase;

    private function contactPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'subject' => 'A question about an order',
            'message' => 'Could you tell me whether this item ships to the Netherlands?',
        ], $overrides);
    }

    private function currentCartHash(Product $product, int $quantity): string
    {
        return (string) $this->postJson('/api/v1/cart/validate', [
            'items' => [['productId' => $product->id, 'variantId' => null, 'quantity' => $quantity]],
            'countryCode' => 'US',
        ])->json('data.cartHash');
    }

    private function placeOrder(): Order
    {
        $this->usShippingZone();
        $this->enableStripe();
        $product = $this->publishedProduct(['price_amount' => 2600, 'currency' => 'USD']);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/checkout/initialize', $this->checkoutPayload($product, $this->currentCartHash($product, 2)))
            ->assertCreated();

        return Order::query()->latest('id')->firstOrFail();
    }

    /* ------------------------------------------------------------------ *
     * Contact inquiries
     * ------------------------------------------------------------------ */

    public function test_the_contact_form_stores_the_inquiry_and_queues_both_emails(): void
    {
        $this->seedStore();
        $this->settings()->set('store.contact_email', 'owner@happykoop.test');
        Mail::fake();

        $response = $this->postJson('/api/v1/contact', $this->contactPayload())
            ->assertCreated()
            ->assertJsonStructure(['data' => ['reference', 'receivedAt']]);

        $inquiry = ContactInquiry::query()->firstOrFail();

        // The record is the source of truth, and it is stamped as notified so
        // a replay cannot produce a second pair of emails.
        $this->assertSame('ada@example.test', $inquiry->email);
        $this->assertSame($response->json('data.reference'), $inquiry->reference);
        $this->assertNotNull($inquiry->notified_at);

        Mail::assertQueued(ContactInquiryAdminMail::class, 1);
        Mail::assertQueued(
            ContactInquiryAdminMail::class,
            static fn (ContactInquiryAdminMail $mail): bool => $mail->hasTo('owner@happykoop.test')
        );
        Mail::assertQueued(
            ContactInquiryAckMail::class,
            static fn (ContactInquiryAckMail $mail): bool => $mail->hasTo('ada@example.test')
        );
    }

    public function test_the_visitor_cannot_choose_who_the_inquiry_is_emailed_to(): void
    {
        $this->seedStore();
        $this->settings()->set('store.contact_email', 'owner@happykoop.test');
        Mail::fake();

        // Hostile extras: a recipient the sender would like the store's mail
        // server to deliver to. There is no such field, so it is ignored.
        $this->postJson('/api/v1/contact', $this->contactPayload([
            'to' => 'attacker@example.test',
            'cc' => 'attacker@example.test',
        ]))->assertCreated();

        Mail::assertQueued(
            ContactInquiryAdminMail::class,
            static fn (ContactInquiryAdminMail $mail): bool => $mail->hasTo('owner@happykoop.test')
                && ! $mail->hasTo('attacker@example.test')
                && ! $mail->hasCc('attacker@example.test')
        );
    }

    public function test_a_filled_honeypot_is_rejected_and_nothing_is_stored(): void
    {
        $this->seedStore();
        Mail::fake();

        $this->postJson('/api/v1/contact', $this->contactPayload(['website' => 'http://spam.example']))
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED');

        $this->assertSame(0, ContactInquiry::query()->count());
        Mail::assertNothingQueued();
    }

    public function test_a_message_that_is_only_markup_is_a_validation_error_not_a_server_error(): void
    {
        $this->seedStore();
        Mail::fake();

        // Sanitising strips the tags, which would leave an empty message. The
        // Form Request sanitises BEFORE validating, so this is a clean 422
        // rather than a null reaching a NOT NULL column.
        $this->postJson('/api/v1/contact', $this->contactPayload(['message' => '<div></div><br>']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message']);

        $this->assertSame(0, ContactInquiry::query()->count());
    }

    public function test_a_replayed_contact_notification_does_not_send_a_second_pair(): void
    {
        $this->seedStore();
        Mail::fake();

        $this->postJson('/api/v1/contact', $this->contactPayload())->assertCreated();
        $inquiry = ContactInquiry::query()->firstOrFail();

        Mail::assertQueued(ContactInquiryAckMail::class, 1);

        // The same event again - what a retried job or a double dispatch looks
        // like. `notified_at` is already claimed, so nothing is sent.
        \App\Events\ContactInquiryReceived::dispatch($inquiry->refresh());

        Mail::assertQueued(ContactInquiryAckMail::class, 1);
        Mail::assertQueued(ContactInquiryAdminMail::class, 1);
    }

    /* ------------------------------------------------------------------ *
     * Order placement
     * ------------------------------------------------------------------ */

    public function test_placing_an_order_queues_the_admin_notification_and_the_customer_confirmation(): void
    {
        $this->seedStore();
        $this->settings()->set('store.contact_email', 'owner@happykoop.test');
        Mail::fake();

        $order = $this->placeOrder();

        $this->assertNotNull($order->refresh()->placed_notified_at);

        Mail::assertQueued(OrderPlacedAdminMail::class, 1);
        Mail::assertQueued(
            OrderConfirmationMail::class,
            static fn (OrderConfirmationMail $mail): bool => $mail->hasTo('buyer@example.test')
        );

        // Creating an order is not delivering it.
        Mail::assertNotQueued(OrderDeliveredMail::class);
    }

    public function test_a_replayed_order_placed_event_does_not_resend_the_confirmation(): void
    {
        $this->seedStore();
        Mail::fake();

        $order = $this->placeOrder();
        Mail::assertQueued(OrderConfirmationMail::class, 1);

        // A replayed webhook or a retried job.
        OrderPlaced::dispatch($order->refresh());

        Mail::assertQueued(OrderConfirmationMail::class, 1);
        Mail::assertQueued(OrderPlacedAdminMail::class, 1);
    }

    /* ------------------------------------------------------------------ *
     * Order delivery
     * ------------------------------------------------------------------ */

    public function test_the_delivered_transition_sends_the_delivery_email_exactly_once(): void
    {
        $this->seedStore();
        Mail::fake();

        $order = $this->placeOrder();
        $machine = app(PaymentStatusMachine::class);

        // The real path: pending -> processing -> shipped -> delivered. None of
        // the earlier steps may announce a delivery.
        $machine->applyStatus($order->refresh(), Order::STATUS_PROCESSING);
        $machine->applyStatus($order->refresh(), Order::STATUS_SHIPPED);
        Mail::assertNotQueued(OrderDeliveredMail::class);

        $machine->applyStatus($order->refresh(), Order::STATUS_DELIVERED);

        Mail::assertQueued(
            OrderDeliveredMail::class,
            static fn (OrderDeliveredMail $mail): bool => $mail->hasTo('buyer@example.test')
        );
        Mail::assertQueued(OrderDeliveredMail::class, 1);

        $delivered = $order->refresh();
        $this->assertNotNull($delivered->delivered_at, 'the delivery date is recorded, not invented');
        $this->assertNotNull($delivered->delivery_notified_at);

        // Saving the same status again: the machine reports no change and no
        // second email goes out.
        $this->assertFalse($machine->applyStatus($delivered, Order::STATUS_DELIVERED));
        Mail::assertQueued(OrderDeliveredMail::class, 1);
    }

    public function test_the_admin_status_endpoint_sends_one_delivery_email_when_saved_twice(): void
    {
        $admin = $this->adminUser();
        Mail::fake();

        $order = $this->placeOrder();
        $machine = app(PaymentStatusMachine::class);
        $machine->applyStatus($order->refresh(), Order::STATUS_PROCESSING);
        $machine->applyStatus($order->refresh(), Order::STATUS_SHIPPED);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'delivered'])
            ->assertOk();

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'delivered'])
            ->assertOk();

        Mail::assertQueued(OrderDeliveredMail::class, 1);
    }
}
