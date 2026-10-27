<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\PageRequest;
use App\Http\Resources\PageResource;
use App\Models\Page;
use App\Services\AuditLogger;
use App\Support\Html;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends ApiController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Page::class);

        return $this->paginated(
            Page::query()->orderBy('slug')->paginate($this->perPage($request)),
            static fn (Page $page): array => PageResource::admin($page)
        );
    }

    public function store(PageRequest $request): JsonResponse
    {
        $this->authorize('create', Page::class);

        $page = Page::query()->create($this->attributes($request));

        $this->audit->log('page.created', $request->user()?->id, $page, array_keys($request->validated()), $request);

        return $this->data(PageResource::admin($page), 201);
    }

    public function show(int $id): JsonResponse
    {
        $page = $this->find($id);

        $this->authorize('view', $page);

        return $this->data(PageResource::admin($page));
    }

    public function update(PageRequest $request, int $id): JsonResponse
    {
        $page = $this->find($id);

        $this->authorize('update', $page);

        $attributes = $this->attributes($request);

        $page->fill($attributes)->save();

        $this->audit->log('page.updated', $request->user()?->id, $page, array_keys($attributes), $request);

        return $this->data(PageResource::admin($page->refresh()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $page = $this->find($id);

        $this->authorize('delete', $page);

        $this->audit->log('page.deleted', $request->user()?->id, $page, ['slug'], $request);

        $page->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(PageRequest $request): array
    {
        $input = $request->validated();
        $out = [];

        foreach (['slug' => 'slug', 'isPublished' => 'is_published', 'seoNoindex' => 'seo_noindex', 'publishedAt' => 'published_at'] as $inputKey => $column) {
            if (array_key_exists($inputKey, $input)) {
                $out[$column] = $input[$inputKey];
            }
        }

        foreach ([
            'title' => 'title',
            'excerpt' => 'excerpt',
            'seoTitle' => 'seo_title',
            'seoDescription' => 'seo_description',
        ] as $inputKey => $column) {
            if (array_key_exists($inputKey, $input)) {
                $out[$column] = Html::text($input[$inputKey], 500);
            }
        }

        // Admin-authored HTML is sanitized before storage.
        if (array_key_exists('body', $input)) {
            $out['body'] = Html::sanitize($input['body']);
        }

        if (array_key_exists('translations', $input)) {
            $out['translations'] = $this->translations((array) ($input['translations'] ?? []));
        }

        if (($out['is_published'] ?? false) && ! array_key_exists('published_at', $out)) {
            $out['published_at'] = now();
        }

        return $out;
    }

    /**
     * Cleans the per-language content the same way as the default language.
     * Empty fields are dropped, so they fall back to the default language.
     *
     * @param  array<string, mixed>  $input  camelCase, keyed by locale
     * @return array<string, array<string, string>>|null
     */
    private function translations(array $input): ?array
    {
        $out = [];

        foreach (Page::TRANSLATION_LOCALES as $locale) {
            $fields = (array) ($input[$locale] ?? []);
            $clean = [];

            foreach (['title' => 'title', 'excerpt' => 'excerpt', 'seoTitle' => 'seo_title', 'seoDescription' => 'seo_description'] as $inputKey => $key) {
                $value = Html::text($fields[$inputKey] ?? null, 500);

                if ($value !== null && trim($value) !== '') {
                    $clean[$key] = $value;
                }
            }

            $body = Html::sanitize($fields['body'] ?? null);

            if ($body !== null && trim(strip_tags($body)) !== '') {
                $clean['body'] = $body;
            }

            if ($clean !== []) {
                $out[$locale] = $clean;
            }
        }

        return $out === [] ? null : $out;
    }

    private function find(int $id): Page
    {
        /** @var Page|null $page */
        $page = Page::query()->find($id);

        abort_if($page === null, 404);

        return $page;
    }
}
