<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 64)->unique();
            $table->string('access_token', 80)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('phone', 32)->nullable();
            $table->char('currency', 3)->default('USD');

            $table->unsignedBigInteger('subtotal_amount')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('shipping_amount')->nullable();
            $table->unsignedBigInteger('tax_amount')->nullable();
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->string('coupon_code', 64)->nullable();

            $table->enum('status', [
                'pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded',
            ])->default('pending');
            $table->enum('payment_status', [
                'unpaid', 'authorized', 'paid', 'partially_refunded', 'refunded', 'failed', 'cancelled',
            ])->default('unpaid');
            $table->string('payment_provider', 32)->nullable();

            $table->string('shipping_method_code', 64)->nullable();
            $table->string('shipping_method_label')->nullable();
            $table->json('shipping_address');
            $table->json('billing_address');

            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();

            $table->string('carrier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('tracking_url', 1000)->nullable();

            $table->unsignedBigInteger('refunded_amount')->default(0);

            $table->timestamp('placed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('inventory_committed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('payment_status');
            $table->index('user_id');
            $table->index('created_at');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
