<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('mode', 16);
            $table->string('status', 48)->default('requires_payment');
            $table->string('provider_reference')->nullable()->index();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->string('error_code', 128)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'provider'], 'payment_attempts_order_provider_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
