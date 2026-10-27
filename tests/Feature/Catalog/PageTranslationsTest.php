<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsStoreFixtures;
use Tests\TestCase;

/**
 * CMS pages in more than one language: ?locale=nl returns the Dutch version,
 * and anything not translated falls back to the default language.
 */
class PageTranslationsTest extends TestCase
{
    use BuildsStoreFixtures, RefreshDatabase;

    private function page(?array $translations = null): Page
    {
        return Page::query()->create([
            'slug' => 'shipping',
            'title' => 'Shipping',
            'body' => '<p>We ship across Belgium.</p>',
            'excerpt' => 'Where we ship.',
            'is_published' => true,
            'published_at' => now()->subMinute(),
            'seo_noindex' => false,
            'translations' => $translations,
        ]);
    }

    public function test_dutch_is_returned_when_asked_for(): void
    {
        $this->seedStore();
        $this->page(['nl' => ['title' => 'Verzending', 'body' => '<p>We verzenden in heel België.</p>']]);

        $this->getJson('/api/v1/pages/shipping?locale=nl')
            ->assertOk()
            ->assertJsonPath('data.title', 'Verzending')
            ->assertJsonPath('data.body', '<p>We verzenden in heel België.</p>')
            // Not translated, so the English excerpt is used.
            ->assertJsonPath('data.excerpt', 'Where we ship.');

        $this->getJson('/api/v1/pages?locale=nl')->assertJsonPath('data.0.title', 'Verzending');
    }

    public function test_english_is_the_default_and_the_fallback(): void
    {
        $this->seedStore();
        $this->page(['nl' => ['title' => 'Verzending']]);

        $this->getJson('/api/v1/pages/shipping')->assertJsonPath('data.title', 'Shipping');
        $this->getJson('/api/v1/pages/shipping?locale=fr')->assertJsonPath('data.title', 'Shipping');
        $this->getJson('/api/v1/pages/shipping?locale=nl')
            ->assertJsonPath('data.body', '<p>We ship across Belgium.</p>');
    }

    public function test_admin_saves_sanitized_translations(): void
    {
        Sanctum::actingAs($this->adminUser(), ['customer', 'admin']);
        $page = $this->page();

        $this->patchJson('/api/v1/admin/pages/'.$page->id, [
            'translations' => ['nl' => [
                'title' => 'Verzending',
                'body' => '<p>Hallo</p><script>alert(1)</script>',
                'excerpt' => '',
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.translations.nl.title', 'Verzending')
            ->assertJsonPath('data.translations.nl.excerpt', null);

        $stored = $page->refresh()->translations['nl'];
        $this->assertStringNotContainsString('<script', $stored['body']);
        $this->assertArrayNotHasKey('excerpt', $stored);
    }

    public function test_an_unknown_language_is_rejected(): void
    {
        Sanctum::actingAs($this->adminUser(), ['customer', 'admin']);
        $page = $this->page();

        $this->patchJson('/api/v1/admin/pages/'.$page->id, ['translations' => ['fr' => ['title' => 'Livraison']]])
            ->assertUnprocessable();
    }
}
