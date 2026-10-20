<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            // The idempotency guarantee: a replayed provider event can only
            // ever be inserted once.
            $table->string('event_id')->unique();
            $table->string('type');
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->json('payload');
            $table->string('status', 32)->default('received');
            $table->timestamp('processed_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamps();

            $table->index(['provider', 'type'], 'payment_events_provider_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
