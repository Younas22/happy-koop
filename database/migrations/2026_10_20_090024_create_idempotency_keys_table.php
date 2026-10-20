<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key', 128)->unique();
            $table->string('scope', 64);
            $table->string('request_hash', 64);
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->json('response_body')->nullable();
            $table->string('status', 32)->default('in_progress');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['scope', 'expires_at'], 'idempotency_keys_scope_expires_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
