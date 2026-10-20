<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\HomeSectionsRequest;
use App\Models\HomeSection;
use App\Services\AuditLogger;
use App\Support\Html;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HomeSectionController extends ApiController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', HomeSection::class);

        return $this->data(
            HomeSection::query()->orderBy('sort_order')->orderBy('id')->get()
                ->map(static fn (HomeSection $section): array => [
                    'id' => (int) $section->id,
                    'type' => $section->type,
                    'heading' => $section->heading,
                    'config' => (array) ($section->config ?? []),
                    'sortOrder' => (int) $section->sort_order,
                    'isActive' => (bool) $section->is_active,
                ])->values()->all()
        );
    }

    /**
     * Replaces the whole homepage composition atomically.
     */
    public function replace(HomeSectionsRequest $request): JsonResponse
    {
        $this->authorize('create', HomeSection::class);

        DB::transaction(function () use ($request): void {
            HomeSection::query()->delete();

            foreach ((array) $request->input('sections', []) as $index => $section) {
                $config = (array) ($section['config'] ?? []);

                if (($section['type'] ?? null) === HomeSection::TYPE_RICH_TEXT
                    && isset($config['html'])) {
                    $config['html'] = Html::sanitize((string) $config['html']);
                }

                HomeSection::query()->create([
                    'type' => (string) $section['type'],
                    'heading' => Html::text($section['heading'] ?? null, 160),
                    'config' => $config,
                    'sort_order' => $index,
                    'is_active' => (bool) ($section['isActive'] ?? true),
                ]);
            }
        });

        $this->audit->log('home_sections.replaced', $request->user()?->id, null, ['sections'], $request);

        return $this->index();
    }
}
