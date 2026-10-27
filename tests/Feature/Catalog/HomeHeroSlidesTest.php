<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\HomeSection;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsStoreFixtures;
use Tests\TestCase;

/**
 * The hero takes several images (a slider). Older sections stored a single
 * `imageMediaId` and must keep working.
 */
class HomeHeroSlidesTest extends TestCase
{
    use BuildsStoreFixtures, RefreshDatabase;

    private function hero(array $config): void
    {
        HomeSection::query()->create([
            'type' => HomeSection::TYPE_HERO,
            'heading' => null,
            'config' => ['headline' => 'Hello'] + $config,
            'sort_order' => 0,
            'is_active' => true,
        ]);
    }

    public function test_hero_lists_its_slides_in_the_admin_order(): void
    {
        $this->seedStore();
        [$a, $b, $c] = Media::factory()->count(3)->create()->all();
        $this->hero(['imageMediaIds' => [$c->id, $a->id, $b->id]]);

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('data.hero.imageUrls', [$c->url(), $a->url(), $b->url()])
            ->assertJsonPath('data.hero.imageUrl', $c->url());
    }

    public function test_a_single_legacy_image_becomes_a_one_slide_list(): void
    {
        $this->seedStore();
        $media = Media::factory()->create();
        $this->hero(['imageMediaId' => $media->id]);

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('data.hero.imageUrls', [$media->url()]);
    }

    public function test_deleted_media_is_skipped(): void
    {
        $this->seedStore();
        $media = Media::factory()->create();
        $this->hero(['imageMediaIds' => [999999, $media->id]]);

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('data.hero.imageUrls', [$media->url()]);
    }

    public function test_admin_cannot_save_more_than_ten_slides(): void
    {
        Sanctum::actingAs($this->adminUser(), ['customer', 'admin']);
        $ids = Media::factory()->count(11)->create()->pluck('id')->all();

        $this->putJson('/api/v1/admin/home-sections', [
            'sections' => [['type' => 'hero', 'config' => ['headline' => 'Hi', 'imageMediaIds' => $ids]]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sections.0.config.imageMediaIds');
    }
}
