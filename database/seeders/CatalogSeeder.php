<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * A small sample catalogue.
 *
 * Every product is named as a sample and carries only honest placeholder
 * copy. Deliberately NOT seeded: reviews, ratings, sales counts,
 * certifications, compare-at "discount" prices and delivery promises.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Kitchen', 'slug' => 'kitchen', 'sort_order' => 1],
            ['name' => 'Home office', 'slug' => 'home-office', 'sort_order' => 2],
            ['name' => 'Everyday carry', 'slug' => 'everyday-carry', 'sort_order' => 3],
        ];

        $categoryIds = [];

        foreach ($categories as $category) {
            $model = Category::query()->firstOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'is_active' => true,
                    'sort_order' => $category['sort_order'],
                    'description' => null,
                ]
            );

            $categoryIds[$category['slug']] = $model->id;
        }

        $products = [
            [
                'slug' => 'sample-pour-over-dripper',
                'name' => 'Sample: Ceramic Pour-Over Dripper',
                'sku' => 'SAMPLE-DRIP-01',
                'short_description' => 'Sample catalogue item. Single-cup dripper in matte stoneware.',
                'price_amount' => 2900,
                'stock_quantity' => 24,
                'low_stock_threshold' => 5,
                'weight_grams' => 420,
                'length_mm' => 140,
                'width_mm' => 140,
                'height_mm' => 110,
                'category' => 'kitchen',
                'specifications' => [
                    ['group' => 'Dimensions', 'label' => 'Height', 'value' => '95', 'unit' => 'mm'],
                    ['group' => 'Dimensions', 'label' => 'Diameter', 'value' => '115', 'unit' => 'mm'],
                    ['group' => 'Materials & care', 'label' => 'Material', 'value' => 'Stoneware', 'unit' => null],
                ],
                'whats_included' => ['1 x ceramic dripper', '1 x stainless stand'],
            ],
            [
                'slug' => 'sample-desk-organiser',
                'name' => 'Sample: Felt Desk Organiser',
                'sku' => 'SAMPLE-DESK-01',
                'short_description' => 'Sample catalogue item. Three-compartment desk tray.',
                'price_amount' => 3400,
                'stock_quantity' => 12,
                'low_stock_threshold' => 3,
                'weight_grams' => 300,
                'length_mm' => 300,
                'width_mm' => 150,
                'height_mm' => 60,
                'category' => 'home-office',
                'specifications' => [
                    ['group' => 'Dimensions', 'label' => 'Width', 'value' => '300', 'unit' => 'mm'],
                    ['group' => 'Materials & care', 'label' => 'Material', 'value' => 'Recycled felt', 'unit' => null],
                ],
                'whats_included' => ['1 x desk organiser'],
            ],
            [
                'slug' => 'sample-canvas-tote',
                'name' => 'Sample: Heavy Canvas Tote',
                'sku' => 'SAMPLE-TOTE-01',
                'short_description' => 'Sample catalogue item. Everyday tote in undyed canvas.',
                'price_amount' => 4200,
                'stock_quantity' => 0,
                'low_stock_threshold' => null,
                'weight_grams' => 550,
                'length_mm' => 400,
                'width_mm' => 120,
                'height_mm' => 380,
                'category' => 'everyday-carry',
                'specifications' => [
                    ['group' => 'Materials & care', 'label' => 'Material', 'value' => 'Cotton canvas', 'unit' => null],
                ],
                'whats_included' => ['1 x canvas tote'],
            ],
            [
                'slug' => 'sample-insulated-bottle',
                'name' => 'Sample: Insulated Steel Bottle',
                'sku' => 'SAMPLE-BOTL-01',
                'short_description' => 'Sample catalogue item. 500 ml double-walled bottle.',
                'price_amount' => 2600,
                'stock_quantity' => 40,
                'low_stock_threshold' => 8,
                'weight_grams' => 340,
                'length_mm' => 70,
                'width_mm' => 70,
                'height_mm' => 250,
                'category' => 'everyday-carry',
                'specifications' => [
                    ['group' => 'Dimensions', 'label' => 'Capacity', 'value' => '500', 'unit' => 'ml'],
                    ['group' => 'Materials & care', 'label' => 'Material', 'value' => 'Stainless steel', 'unit' => null],
                ],
                'whats_included' => ['1 x bottle', '1 x spare seal'],
            ],
        ];

        foreach ($products as $data) {
            $specifications = $data['specifications'];
            $category = $data['category'];
            unset($data['specifications'], $data['category']);

            /** @var Product $product */
            $product = Product::query()->firstOrCreate(
                ['slug' => $data['slug']],
                $data + [
                    'description' => '<p>Sample product used for local development. Replace this copy with real product information before publishing.</p>',
                    'status' => Product::STATUS_PUBLISHED,
                    'currency' => 'USD',
                    // No compare_at_amount: a fake "was" price is a fake discount.
                    'compare_at_amount' => null,
                    'inventory_policy' => Product::POLICY_TRACK,
                    'returns_window_days' => 30,
                    'published_at' => now(),
                    'badges' => [],
                    'value_props' => [],
                ]
            );

            $product->categories()->syncWithoutDetaching([$categoryIds[$category]]);

            if ($product->specifications()->count() === 0) {
                foreach ($specifications as $index => $spec) {
                    $product->specifications()->create($spec + ['sort_order' => $index]);
                }
            }
        }

        $this->command?->info('Sample catalogue ensured (4 clearly-labelled sample products, no reviews).');
    }
}
