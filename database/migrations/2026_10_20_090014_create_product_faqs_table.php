<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('question', 500);
            $table->text('answer');
            $table->integer('sort_order')->default(0);

            $table->index(['product_id', 'sort_order'], 'product_faqs_product_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_faqs');
    }
};
