<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsStoreFixtures;
use Tests\TestCase;

class PageIndexTest extends TestCase
{
    use BuildsStoreFixtures, RefreshDatabase;

    private function page(array $attributes = []): Page
    {
        return Page::query()->create(array_merge([
            'slug' => 'about',
            'title' => 'About',
            'body' => '<p>Body</p>',
            'excerpt' => null,
            'is_published' => true,
            'published_at' => now(),
            'seo_noindex' => false,
        ], $attributes));
    }

    public function test_it_lists_published_pages_without_their_bodies(): void
    {
        $this->seedStore();
        $this->page(['slug' => 'shipping', 'title' => 'Shipping', 'excerpt' => 'How we ship.']);

        $response = $this->getJson('/api/v1/pages')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'slug', 'title', 'excerpt', 'updatedAt']]])
            ->assertJsonPath('data.0.slug', 'shipping')
            ->assertJsonPath('data.0.excerpt', 'How we ship.');

        // The body is deliberately absent: this list only builds links, and a
        // navigation request has no reason to ship every page's HTML.
        $this->assertArrayNotHasKey('body', $response->json('data.0'));
    }

    public function test_it_orders_by_title(): void
    {
        $this->seedStore();
        $this->page(['slug' => 'terms', 'title' => 'Terms of service']);
        $this->page(['slug' => 'about', 'title' => 'About']);

        $this->getJson('/api/v1/pages')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'about')
            ->assertJsonPath('data.1.slug', 'terms');
    }

    public function test_it_hides_unpublished_scheduled_and_noindex_pages(): void
    {
        $this->seedStore();
        $this->page(['slug' => 'visible', 'title' => 'Visible']);
        $this->page(['slug' => 'draft', 'title' => 'Draft', 'is_published' => false]);
        $this->page(['slug' => 'later', 'title' => 'Later', 'published_at' => now()->addDay()]);
        $this->page(['slug' => 'hidden', 'title' => 'Hidden', 'seo_noindex' => true]);

        $slugs = collect($this->getJson('/api/v1/pages')->assertOk()->json('data'))
            ->pluck('slug')
            ->all();

        $this->assertSame(['visible'], $slugs);
    }
}
