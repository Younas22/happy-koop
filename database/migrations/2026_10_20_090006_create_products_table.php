<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->nullable()->unique();
            $table->string('brand')->nullable();
            $table->string('short_description', 500)->nullable();
            $table->longText('description')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');

            $table->unsignedBigInteger('price_amount')->default(0);
            $table->unsignedBigInteger('compare_at_amount')->nullable();
            // PRIVATE: admin-only. Must never appear in a public resource.
            $table->unsignedBigInteger('cost_amount')->nullable();
            $table->char('currency', 3)->default('USD');

            $table->enum('inventory_policy', ['track', 'continue', 'deny'])->default('track');
            $table->integer('stock_quantity')->default(0);
            $table->integer('low_stock_threshold')->nullable();

            $table->unsignedInteger('weight_grams')->nullable();
            $table->unsignedInteger('length_mm')->nullable();
            $table->unsignedInteger('width_mm')->nullable();
            $table->unsignedInteger('height_mm')->nullable();

            $table->json('value_props')->nullable();
            $table->json('whats_included')->nullable();
            $table->text('shipping_summary')->nullable();
            $table->text('returns_summary')->nullable();
            $table->text('warranty_text')->nullable();
            $table->unsignedInteger('returns_window_days')->nullable();
            $table->json('badges')->nullable();

            $table->foreignId('primary_media_id')->nullable()->constrained('media')->nullOnDelete();

            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->boolean('seo_noindex')->default(false);

            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at'], 'products_status_published_index');
            $table->index('price_amount');
            $table->index('name');
            $table->index('brand');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
