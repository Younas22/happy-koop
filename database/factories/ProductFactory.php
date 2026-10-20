<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = 'Test '.$this->faker->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'sku' => 'TEST-'.Str::upper(Str::random(8)),
            'short_description' => 'Fixture product.',
            'description' => '<p>Fixture product.</p>',
            'status' => Product::STATUS_PUBLISHED,
            'price_amount' => 2900,
            'compare_at_amount' => null,
            // Private: public resources must never expose this.
            'cost_amount' => 1200,
            'currency' => 'USD',
            'inventory_policy' => Product::POLICY_TRACK,
            'stock_quantity' => 25,
            'low_stock_threshold' => null,
            'weight_grams' => 420,
            'published_at' => now()->subDay(),
            'badges' => [],
            'value_props' => [],
            'whats_included' => [],
        ];
    }

    public function draft(): self
    {
        return $this->state(fn (): array => [
            'status' => Product::STATUS_DRAFT,
            'published_at' => null,
        ]);
    }

    public function outOfStock(): self
    {
        return $this->state(fn (): array => ['stock_quantity' => 0]);
    }
}
