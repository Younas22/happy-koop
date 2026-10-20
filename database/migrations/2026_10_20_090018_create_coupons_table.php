<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->enum('type', ['fixed', 'percent']);
            // `fixed` -> minor units in `currency`; `percent` -> whole percent.
            $table->unsignedInteger('value');
            $table->char('currency', 3)->nullable();
            $table->unsignedBigInteger('min_subtotal_amount')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_user')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at'], 'coupons_active_window_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
