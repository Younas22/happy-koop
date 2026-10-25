<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Exceptions\ApiException;
use App\Events\OrderDelivered;
use App\Models\Order;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;

/**
 * Enforces the allowed order and payment state transitions
 * (api-contract.md 6). An invalid transition is a 409 CONFLICT, never a
 * silent overwrite.
 */
class PaymentStatusMachine
{
    /**
     * @var array<string, list<string>>
     */
    public const PAYMENT_TRANSITIONS = [
        Order::PAYMENT_UNPAID => [
            Order::PAYMENT_AUTHORIZED,
            Order::PAYMENT_PAID,
            Order::PAYMENT_FAILED,
            Order::PAYMENT_CANCELLED,
        ],
        Order::PAYMENT_AUTHORIZED => [
            Order::PAYMENT_PAID,
            Order::PAYMENT_FAILED,
            Order::PAYMENT_CANCELLED,
            Order::PAYMENT_REFUNDED,
        ],
        Order::PAYMENT_PAID => [
            Order::PAYMENT_PARTIALLY_REFUNDED,
            Order::PAYMENT_REFUNDED,
        ],
        Order::PAYMENT_PARTIALLY_REFUNDED => [
            Order::PAYMENT_PARTIALLY_REFUNDED,
            Order::PAYMENT_REFUNDED,
        ],
        Order::PAYMENT_REFUNDED => [],
        // A failed attempt may be retried by the customer.
        Order::PAYMENT_FAILED => [
            Order::PAYMENT_AUTHORIZED,
            Order::PAYMENT_PAID,
            Order::PAYMENT_CANCELLED,
        ],
        Order::PAYMENT_CANCELLED => [],
    ];

    /**
     * @var array<string, list<string>>
     */
    public const STATUS_TRANSITIONS = [
        Order::STATUS_PENDING => [
            Order::STATUS_PROCESSING,
            Order::STATUS_CANCELLED,
        ],
        Order::STATUS_PROCESSING => [
            Order::STATUS_SHIPPED,
            Order::STATUS_CANCELLED,
            Order::STATUS_REFUNDED,
        ],
        Order::STATUS_SHIPPED => [
            Order::STATUS_DELIVERED,
            Order::STATUS_REFUNDED,
        ],
        Order::STATUS_DELIVERED => [
            Order::STATUS_REFUNDED,
        ],
        Order::STATUS_CANCELLED => [],
        Order::STATUS_REFUNDED => [],
    ];

    public function __construct(private readonly InventoryService $inventory) {}

    public function canTransitionPayment(string $from, string $to): bool
    {
        return $from === $to || in_array($to, self::PAYMENT_TRANSITIONS[$from] ?? [], true);
    }

    public function canTransitionStatus(string $from, string $to): bool
    {
        return $from === $to || in_array($to, self::STATUS_TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Applies a payment-status transition, committing inventory on the first
     * move into paid/authorized.
     *
     * @return bool true when the order actually changed
     */
    public function applyPayment(Order $order, string $to, ?string $provider = null): bool
    {
        $from = (string) $order->payment_status;

        if (! $this->canTransitionPayment($from, $to)) {
            throw ApiException::conflict(
                "This order cannot move from payment status [{$from}] to [{$to}]."
            );
        }

        if ($from === $to) {
            return false;
        }

        DB::transaction(function () use ($order, $to, $provider): void {
            $attributes = ['payment_status' => $to];

            if ($provider !== null) {
                $attributes['payment_provider'] = $provider;
            }

            if (in_array($to, [Order::PAYMENT_PAID, Order::PAYMENT_AUTHORIZED], true)) {
                $attributes['paid_at'] = $order->paid_at ?? now();
                $attributes['placed_at'] = $order->placed_at ?? now();

                if ($order->status === Order::STATUS_PENDING) {
                    $attributes['status'] = Order::STATUS_PROCESSING;
                }
            }

            if ($to === Order::PAYMENT_CANCELLED) {
                $attributes['cancelled_at'] = $order->cancelled_at ?? now();

                if ($order->status === Order::STATUS_PENDING) {
                    $attributes['status'] = Order::STATUS_CANCELLED;
                }
            }

            $order->forceFill($attributes)->save();
        });

        if (in_array($to, [Order::PAYMENT_PAID, Order::PAYMENT_AUTHORIZED], true)) {
            // Guarded by inventory_committed_at: runs at most once per order.
            $this->inventory->commit($order->refresh());
        }

        if (in_array($to, [Order::PAYMENT_REFUNDED, Order::PAYMENT_CANCELLED], true)) {
            $this->inventory->release($order->refresh());
        }

        return true;
    }

    /**
     * Applies a fulfilment-status transition.
     */
    public function applyStatus(Order $order, string $to): bool
    {
        $from = (string) $order->status;

        if (! $this->canTransitionStatus($from, $to)) {
            throw ApiException::conflict("This order cannot move from status [{$from}] to [{$to}].");
        }

        if ($from === $to) {
            return false;
        }

        $attributes = ['status' => $to];

        if ($to === Order::STATUS_CANCELLED) {
            $attributes['cancelled_at'] = $order->cancelled_at ?? now();
        }

        if ($to === Order::STATUS_DELIVERED) {
            // Recorded here so the delivery email can quote a real date
            // instead of the moment the email happened to be rendered.
            $attributes['delivered_at'] = $order->delivered_at ?? now();
        }

        $order->forceFill($attributes)->save();

        if ($to === Order::STATUS_CANCELLED) {
            $this->inventory->release($order->refresh());
        }

        // Delivery notification. This is the only place an order's status
        // changes, and the guard above already returned when `from === to`,
        // so re-saving Delivered dispatches nothing; the listener additionally
        // claims `delivery_notified_at`, so even a replayed event sends once.
        if ($to === Order::STATUS_DELIVERED) {
            OrderDelivered::dispatch($order->refresh());
        }

        return true;
    }
}
