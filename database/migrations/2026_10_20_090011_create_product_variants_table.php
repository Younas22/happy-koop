<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku')->nullable()->unique();
            $table->unsignedBigInteger('price_amount')->nullable();
            $table->unsignedBigInteger('compare_at_amount')->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->enum('inventory_policy', ['track', 'continue', 'deny'])->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'is_active'], 'product_variants_product_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
