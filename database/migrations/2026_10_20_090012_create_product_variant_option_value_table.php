<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_option_value', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();
            $table->foreignId('product_option_value_id')
                ->constrained('product_option_values')
                ->cascadeOnDelete();

            // Named explicitly: the generated name would exceed MariaDB's
            // 64-character identifier limit.
            $table->unique(
                ['product_variant_id', 'product_option_value_id'],
                'variant_option_value_unique'
            );
            $table->index('product_option_value_id', 'variant_option_value_value_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_option_value');
    }
};
