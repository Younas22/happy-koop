<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Jobs\ProcessPaymentEvent;
use App\Models\PaymentEvent;
use App\Services\Payments\PayPalGateway;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/v1/webhooks/paypal
 *
 * Verified through PayPal's verify-webhook-signature API using the stored
 * webhook id (api-contract.md 6).
 */
class PayPalWebhookController extends ApiController
{
    public function __construct(private readonly PayPalGateway $paypal) {}

    public function handle(Request $request): JsonResponse
    {
        $raw = $request->getContent();

        $headers = [
            'paypal-transmission-id' => $request->header('PAYPAL-TRANSMISSION-ID'),
            'paypal-transmission-time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
            'paypal-cert-url' => $request->header('PAYPAL-CERT-URL'),
            'paypal-auth-algo' => $request->header('PAYPAL-AUTH-ALGO'),
            'paypal-transmission-sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
        ];

        if (! $this->paypal->verifyWebhookSignature($headers, $raw)) {
            throw ApiException::badRequest('Invalid webhook signature.');
        }

        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            throw ApiException::badRequest('Malformed webhook payload.');
        }

        $eventId = (string) ($payload['id'] ?? '');

        if ($eventId === '') {
            return $this->data(['ok' => true, 'ignored' => true]);
        }

        try {
            $record = PaymentEvent::query()->create([
                'provider' => 'paypal',
                'event_id' => $eventId,
                'type' => (string) ($payload['event_type'] ?? 'unknown'),
                'payload' => $payload,
                'status' => PaymentEvent::STATUS_RECEIVED,
            ]);
        } catch (QueryException) {
            return $this->data(['ok' => true, 'duplicate' => true]);
        }

        ProcessPaymentEvent::dispatch((int) $record->id);

        return $this->data(['ok' => true]);
    }
}
