<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

/**
 * Cancels abandoned checkouts.
 *
 * Touches ONLY orders that are still pending AND unpaid AND past their
 * expiry. It never deletes an order, and never touches an order that has any
 * payment history (api-contract.md 5).
 */
class CleanExpiredPendingOrders extends Command
{
    protected $signature = 'happykoop:clean-expired-orders
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Cancel expired pending, unpaid orders';

    public function handle(): int
    {
        $query = Order::query()
            ->where('status', Order::STATUS_PENDING)
            ->where('payment_status', Order::PAYMENT_UNPAID)
            ->whereNull('inventory_committed_at')
            ->whereNull('paid_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("{$count} expired pending order(s) would be cancelled.");

            return self::SUCCESS;
        }

        $cancelled = 0;

        $query->chunkById(100, function ($orders) use (&$cancelled): void {
            foreach ($orders as $order) {
                // Re-check under the current state: a payment may have landed
                // between the query and this write.
                if ($order->status !== Order::STATUS_PENDING
                    || $order->payment_status !== Order::PAYMENT_UNPAID) {
                    continue;
                }

                $order->forceFill([
                    'status' => Order::STATUS_CANCELLED,
                    'payment_status' => Order::PAYMENT_CANCELLED,
                    'cancelled_at' => now(),
                ])->save();

                $cancelled++;
            }
        });

        $this->info("Cancelled {$cancelled} expired pending order(s).");

        return self::SUCCESS;
    }
}
