<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('country_codes');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained('shipping_zones')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('label');
            $table->string('description')->nullable();
            $table->unsignedBigInteger('price_amount')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->unsignedBigInteger('min_subtotal_amount')->nullable();
            $table->unsignedBigInteger('free_above_amount')->nullable();
            $table->unsignedInteger('estimate_min_days')->nullable();
            $table->unsignedInteger('estimate_max_days')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['shipping_zone_id', 'code'], 'shipping_rates_zone_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
        Schema::dropIfExists('shipping_zones');
    }
};
