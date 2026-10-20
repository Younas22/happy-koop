<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_option_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_option_id')->constrained('product_options')->cascadeOnDelete();
            $table->string('label');
            $table->string('value');
            $table->char('swatch_hex', 7)->nullable();
            $table->integer('sort_order')->default(0);

            $table->unique(['product_option_id', 'value'], 'product_option_values_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_option_values');
    }
};
