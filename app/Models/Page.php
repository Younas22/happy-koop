<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;

    /** Languages a page can be translated into, besides the default one. */
    public const TRANSLATION_LOCALES = ['nl'];

    protected $fillable = [
        'slug', 'title', 'body', 'excerpt', 'seo_title', 'seo_description',
        'seo_noindex', 'is_published', 'published_at', 'translations',
    ];

    protected function casts(): array
    {
        return [
            'seo_noindex' => 'boolean',
            'is_published' => 'boolean',
            'translations' => 'array',
            'published_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * A field in the given language, falling back to the default-language
     * column when there is no translation (or it is empty).
     */
    public function localized(string $field, ?string $locale): ?string
    {
        $value = $this->getAttribute($field);
        $fallback = $value === null ? null : (string) $value;

        if ($locale === null || ! in_array($locale, self::TRANSLATION_LOCALES, true)) {
            return $fallback;
        }

        $translated = $this->translations[$locale][$field] ?? null;

        return is_string($translated) && trim(strip_tags($translated)) !== '' ? $translated : $fallback;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(function (Builder $q): void {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }
}
