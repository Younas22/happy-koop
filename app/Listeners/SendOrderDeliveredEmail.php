<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderDelivered;
use App\Mail\OrderDeliveredMail;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the delivery confirmation, exactly once.
 *
 * Two independent guards, because this is the email a customer notices twice:
 * the status machine only dispatches on a real transition into `delivered`,
 * and the conditional UPDATE here claims `delivery_notified_at` so even a
 * replayed event cannot send a second copy.
 */
class SendOrderDeliveredEmail
{
    public function handle(OrderDelivered $event): void
    {
        $order = $event->order->fresh(['items']) ?? $event->order;

        if ($order->status !== Order::STATUS_DELIVERED) {
            // The order moved on before the listener ran; nothing to announce.
            return;
        }

        $claimed = Order::query()
            ->whereKey($order->getKey())
            ->whereNull('delivery_notified_at')
            ->update(['delivery_notified_at' => now()]);

        if ($claimed !== 1) {
            return;
        }

        if (! filter_var($order->email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('order.delivered_notification.skipped', [
                'reason' => 'order has no valid email address',
                'order_number' => $order->order_number,
            ]);

            return;
        }

        try {
            Mail::to($order->email)->send(new OrderDeliveredMail($order));
        } catch (\Throwable $e) {
            Log::error('order.delivered_notification.failed', [
                'order_number' => $order->order_number,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
