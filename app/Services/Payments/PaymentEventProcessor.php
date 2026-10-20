<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentEvent;
use App\Support\Money;
use Throwable;

/**
 * Applies a recorded provider event to an order.
 *
 * Every event is validated against the STORED order (amount, currency,
 * reference) before any state transition, and the transition itself goes
 * through PaymentStatusMachine. Replays are impossible: the row was inserted
 * under a unique `event_id`, and this runs once per row.
 */
class PaymentEventProcessor
{
    public function __construct(
        private readonly PaymentStatusMachine $machine,
        private readonly PayPalGateway $paypal,
    ) {}

    public function process(PaymentEvent $event): void
    {
        if ($event->status === PaymentEvent::STATUS_PROCESSED) {
            return;
        }

        $event->increment('attempts');

        try {
            $handled = $event->provider === 'stripe'
                ? $this->handleStripe($event)
                : $this->handlePayPal($event);

            $event->forceFill([
                'status' => $handled ? PaymentEvent::STATUS_PROCESSED : PaymentEvent::STATUS_IGNORED,
                'processed_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            report($e);

            $event->forceFill(['status' => PaymentEvent::STATUS_FAILED])->save();

            throw $e;
        }
    }

    private function handleStripe(PaymentEvent $event): bool
    {
        $payload = (array) $event->payload;
        $object = (array) ($payload['data']['object'] ?? []);
        $type = (string) $event->type;

        $order = $this->resolveStripeOrder($object);

        if ($order === null) {
            return false;
        }

        $event->forceFill(['order_id' => $order->id])->save();

        return match ($type) {
            'payment_intent.succeeded' => $this->markPaid(
                $order,
                'stripe',
                (int) ($object['amount_received'] ?? $object['amount'] ?? 0),
                strtoupper((string) ($object['currency'] ?? '')),
                (string) ($object['id'] ?? '')
            ),
            'checkout.session.completed' => ($object['payment_status'] ?? null) === 'paid'
                ? $this->markPaid(
                    $order,
                    'stripe',
                    (int) ($object['amount_total'] ?? 0),
                    strtoupper((string) ($object['currency'] ?? '')),
                    (string) ($object['payment_intent'] ?? $object['id'] ?? '')
                )
                : false,
            'payment_intent.payment_failed' => $this->markFailed($order, 'stripe', $object),
            'payment_intent.canceled' => $this->machine->applyPayment($order, Order::PAYMENT_CANCELLED, 'stripe'),
            'charge.refunded' => $this->applyRefund(
                $order,
                (int) ($object['amount_refunded'] ?? 0),
                strtoupper((string) ($object['currency'] ?? ''))
            ),
            default => false,
        };
    }

    private function handlePayPal(PaymentEvent $event): bool
    {
        $payload = (array) $event->payload;
        $resource = (array) ($payload['resource'] ?? []);
        $type = (string) $event->type;

        $reference = (string) ($resource['custom_id']
            ?? $resource['invoice_id']
            ?? ($resource['supplementary_data']['related_ids']['order_id'] ?? ''));

        $order = $reference === ''
            ? null
            : Order::query()->where('order_number', $reference)->first();

        if ($order === null) {
            return false;
        }

        $event->forceFill(['order_id' => $order->id])->save();

        $amountNode = (array) ($resource['amount'] ?? []);
        $currency = strtoupper((string) ($amountNode['currency_code'] ?? ''));
        $minor = $this->paypal->minorUnits((string) ($amountNode['value'] ?? ''), $currency ?: $order->currency);

        return match ($type) {
            'PAYMENT.CAPTURE.COMPLETED' => $this->markPaid(
                $order,
                'paypal',
                $minor,
                $currency,
                (string) ($resource['id'] ?? '')
            ),
            'PAYMENT.CAPTURE.DENIED', 'PAYMENT.CAPTURE.DECLINED' => $this->markFailed($order, 'paypal', $resource),
            'PAYMENT.CAPTURE.REFUNDED' => $this->applyRefund($order, $minor, $currency),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function resolveStripeOrder(array $object): ?Order
    {
        $reference = (string) ($object['metadata']['order_number']
            ?? $object['client_reference_id']
            ?? '');

        if ($reference !== '') {
            return Order::query()->where('order_number', $reference)->first();
        }

        $intentId = (string) ($object['payment_intent'] ?? $object['id'] ?? '');

        if ($intentId === '') {
            return null;
        }

        $attempt = PaymentAttempt::query()->where('provider_reference', $intentId)->latest('id')->first();

        return $attempt?->order;
    }

    /**
     * Amount and currency MUST match the stored order before anything is
     * marked paid.
     */
    private function markPaid(
        Order $order,
        string $provider,
        int $amountMinor,
        string $currency,
        string $reference,
    ): bool {
        if ($currency !== '' && $currency !== $order->currency) {
            $this->recordAttemptFailure($order, $provider, $reference, 'currency_mismatch');

            return false;
        }

        if ($amountMinor !== (int) $order->total_amount) {
            $this->recordAttemptFailure($order, $provider, $reference, 'amount_mismatch');

            return false;
        }

        $this->touchAttempt($order, $provider, $reference, PaymentAttempt::STATUS_SUCCEEDED);

        return $this->machine->applyPayment($order, Order::PAYMENT_PAID, $provider);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function markFailed(Order $order, string $provider, array $object): bool
    {
        $this->touchAttempt(
            $order,
            $provider,
            (string) ($object['id'] ?? ''),
            PaymentAttempt::STATUS_FAILED,
            (string) ($object['last_payment_error']['code'] ?? $object['status_details']['reason'] ?? '')
        );

        return $this->machine->applyPayment($order, Order::PAYMENT_FAILED, $provider);
    }

    private function applyRefund(Order $order, int $refundedMinor, string $currency): bool
    {
        if ($currency !== '' && $currency !== $order->currency) {
            return false;
        }

        if ($refundedMinor <= 0) {
            return false;
        }

        $refunded = Money::of(min($refundedMinor, (int) $order->total_amount), $order->currency);

        $order->forceFill(['refunded_amount' => $refunded->amount])->save();

        $target = $refunded->amount >= (int) $order->total_amount
            ? Order::PAYMENT_REFUNDED
            : Order::PAYMENT_PARTIALLY_REFUNDED;

        return $this->machine->applyPayment($order, $target, $order->payment_provider);
    }

    private function touchAttempt(
        Order $order,
        string $provider,
        string $reference,
        string $status,
        string $errorCode = '',
    ): void {
        $attempt = $order->paymentAttempts()
            ->where('provider', $provider)
            ->latest('id')
            ->first();

        if ($attempt === null) {
            return;
        }

        $attempt->forceFill(array_filter([
            'status' => $status,
            'provider_reference' => $reference !== '' ? $reference : $attempt->provider_reference,
            'error_code' => $errorCode !== '' ? mb_substr($errorCode, 0, 128) : null,
        ], static fn ($value): bool => $value !== null))->save();
    }

    private function recordAttemptFailure(
        Order $order,
        string $provider,
        string $reference,
        string $code,
    ): void {
        $this->touchAttempt($order, $provider, $reference, PaymentAttempt::STATUS_FAILED, $code);

        report(new \RuntimeException(
            "Rejected provider event for order {$order->order_number}: {$code}."
        ));
    }
}
