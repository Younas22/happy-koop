<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\HomeSection;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * CMS pages and the homepage composition.
 *
 * The policy pages carry explicit placeholder text: nothing here claims a
 * legal position the store has not actually adopted.
 */
class CmsSeeder extends Seeder
{
    public function run(): void
    {
        $placeholder = static fn (string $topic): string => '<p>This page is a placeholder. Replace it with your own '
            .$topic.' before launch. The text below is not a legal document and makes no commitment on your behalf.</p>';

        $pages = [
            ['slug' => 'about', 'title' => 'About', 'topic' => 'company information'],
            ['slug' => 'contact', 'title' => 'Contact', 'topic' => 'contact details and support hours'],
            ['slug' => 'privacy', 'title' => 'Privacy policy', 'topic' => 'privacy policy'],
            ['slug' => 'terms', 'title' => 'Terms of service', 'topic' => 'terms of service'],
            ['slug' => 'shipping', 'title' => 'Shipping', 'topic' => 'shipping policy and real delivery times'],
            ['slug' => 'returns', 'title' => 'Returns', 'topic' => 'returns and refunds policy'],
        ];

        foreach ($pages as $page) {
            Page::query()->firstOrCreate(
                ['slug' => $page['slug']],
                [
                    'title' => $page['title'],
                    'body' => $placeholder($page['topic']),
                    'excerpt' => null,
                    'is_published' => true,
                    'published_at' => now(),
                    'seo_noindex' => false,
                ]
            );
        }

        if (HomeSection::query()->count() === 0) {
            HomeSection::query()->create([
                'type' => HomeSection::TYPE_HERO,
                'heading' => null,
                'config' => [
                    'headline' => 'Everyday things, thoughtfully chosen.',
                    'subheadline' => 'This is sample homepage content. Edit it in the admin panel.',
                    'ctaLabel' => 'Shop now',
                    'ctaHref' => '/shop',
                ],
                'sort_order' => 0,
                'is_active' => true,
            ]);

            HomeSection::query()->create([
                'type' => HomeSection::TYPE_CATEGORY_TILES,
                'heading' => 'Browse',
                'config' => [
                    'categoryIds' => Category::query()->active()->orderBy('sort_order')->pluck('id')->all(),
                ],
                'sort_order' => 1,
                'is_active' => true,
            ]);

            HomeSection::query()->create([
                'type' => HomeSection::TYPE_PRODUCT_GRID,
                'heading' => 'Sample products',
                'config' => [
                    'productIds' => Product::query()->published()->orderBy('id')->pluck('id')->all(),
                    'limit' => 8,
                ],
                'sort_order' => 2,
                'is_active' => true,
            ]);

            HomeSection::query()->create([
                'type' => HomeSection::TYPE_BENEFITS,
                'heading' => null,
                'config' => [
                    'items' => [
                        [
                            'icon' => 'truck',
                            'title' => 'Shipping as configured',
                            'body' => 'Rates and delivery windows come from your shipping settings.',
                        ],
                        [
                            'icon' => 'arrow-path',
                            'title' => 'Returns window',
                            'body' => 'Set your real returns window per product in the admin panel.',
                        ],
                        [
                            'icon' => 'lock-closed',
                            'title' => 'Payments stay server-side',
                            'body' => 'Card details are handled by your payment provider, never by this store.',
                        ],
                    ],
                ],
                'sort_order' => 3,
                'is_active' => true,
            ]);
        }

        $this->command?->info('CMS pages and homepage sections ensured.');
    }
}
