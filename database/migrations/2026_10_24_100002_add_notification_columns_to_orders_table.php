<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notification bookkeeping on orders.
 *
 * - `placed_notified_at` makes the order-placed emails idempotent: a retried
 *   checkout request or a replayed provider webhook must not send a customer a
 *   second confirmation for the same order.
 * - `delivered_at` records when the order actually reached the delivered
 *   state, so the delivery email can quote a real date instead of inventing
 *   one.
 * - `delivery_notified_at` makes the delivery email send exactly once, even if
 *   an administrator saves the Delivered status again.
 *
 * Additive and reversible: three nullable timestamps on an existing table. No
 * data is read, rewritten or removed, and `down()` drops only these columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('placed_notified_at')->nullable()->after('placed_at');
            $table->timestamp('delivered_at')->nullable()->after('placed_notified_at');
            $table->timestamp('delivery_notified_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['placed_notified_at', 'delivered_at', 'delivery_notified_at']);
        });
    }
};
