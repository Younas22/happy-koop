<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_specifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('group')->nullable();
            $table->string('label');
            $table->string('value', 1000);
            $table->string('unit', 32)->nullable();
            $table->integer('sort_order')->default(0);

            $table->index(['product_id', 'sort_order'], 'product_specs_product_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_specifications');
    }
};
