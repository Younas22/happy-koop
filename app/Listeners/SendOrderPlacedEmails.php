<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderPlacedAdminMail;
use App\Models\Order;
use App\Services\Mail\MailContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the administrator's new-order notification and the customer's
 * confirmation, exactly once per order.
 *
 * The guard is a conditional UPDATE on `placed_notified_at`, so a retried
 * checkout request, a replayed payment webhook or a second dispatch of the
 * same event cannot produce a second confirmation. A failure to queue an email
 * is logged and never rolls back a valid order.
 */
class SendOrderPlacedEmails
{
    public function __construct(private readonly MailContext $context) {}

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order->fresh(['items']) ?? $event->order;

        $claimed = Order::query()
            ->whereKey($order->getKey())
            ->whereNull('placed_notified_at')
            ->update(['placed_notified_at' => now()]);

        if ($claimed !== 1) {
            return;
        }

        $admin = $this->context->adminRecipient();

        if ($admin === null) {
            Log::warning('order.admin_notification.skipped', [
                'reason' => 'no administrator recipient configured',
                'order_number' => $order->order_number,
            ]);
        } else {
            $this->send(
                static fn () => Mail::to($admin)->send(new OrderPlacedAdminMail($order)),
                'order.admin_notification',
                (string) $order->order_number,
            );
        }

        if (filter_var($order->email, FILTER_VALIDATE_EMAIL)) {
            $this->send(
                static fn () => Mail::to($order->email)->send(new OrderConfirmationMail($order)),
                'order.confirmation',
                (string) $order->order_number,
            );
        } else {
            Log::warning('order.confirmation.skipped', [
                'reason' => 'order has no valid email address',
                'order_number' => $order->order_number,
            ]);
        }
    }

    private function send(callable $dispatch, string $channel, string $orderNumber): void
    {
        try {
            $dispatch();
        } catch (\Throwable $e) {
            Log::error($channel.'.failed', [
                'order_number' => $orderNumber,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
