<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Api\V1\ApiController;
use App\Jobs\ProcessPaymentEvent;
use App\Models\PaymentEvent;
use App\Services\Payments\StripeGateway;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/v1/webhooks/stripe
 *
 * CSRF-exempt, no auth middleware, excluded from CORS. The signature is
 * verified against the RAW request body (api-contract.md 6).
 */
class StripeWebhookController extends ApiController
{
    public function __construct(private readonly StripeGateway $stripe) {}

    public function handle(Request $request): JsonResponse
    {
        // Raw body, never the parsed array: re-encoding would break the HMAC.
        $event = $this->stripe->verifyWebhook(
            $request->getContent(),
            $request->header('Stripe-Signature')
        );

        $eventId = (string) ($event['id'] ?? '');

        if ($eventId === '') {
            return $this->data(['ok' => true, 'ignored' => true]);
        }

        try {
            $record = PaymentEvent::query()->create([
                'provider' => 'stripe',
                'event_id' => $eventId,
                'type' => (string) ($event['type'] ?? 'unknown'),
                'payload' => $event,
                'status' => PaymentEvent::STATUS_RECEIVED,
            ]);
        } catch (QueryException) {
            // Unique index on event_id: a replay is acknowledged and ignored.
            return $this->data(['ok' => true, 'duplicate' => true]);
        }

        ProcessPaymentEvent::dispatch((int) $record->id);

        return $this->data(['ok' => true]);
    }
}
