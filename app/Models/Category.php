<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'parent_id', 'description', 'image_media_id',
        'is_active', 'sort_order', 'seo_title', 'seo_description',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_media_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'category_product');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Breadcrumb trail, root first. Walks up with a depth guard so a cycle
     * introduced by bad data can never hang a request.
     *
     * @return list<self>
     */
    public function ancestors(): array
    {
        $trail = [];
        $node = $this->parent;
        $guard = 0;

        while ($node !== null && $guard < 10) {
            array_unshift($trail, $node);
            $node = $node->parent;
            $guard++;
        }

        return $trail;
    }

    /**
     * This category plus every descendant id.
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $ids = [(int) $this->id];
        $frontier = [(int) $this->id];
        $guard = 0;

        while ($frontier !== [] && $guard < 10) {
            $frontier = self::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            $ids = array_merge($ids, $frontier);
            $guard++;
        }

        return array_values(array_unique($ids));
    }
}
