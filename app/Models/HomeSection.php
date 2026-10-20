<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HomeSection extends Model
{
    public const TYPE_HERO = 'hero';

    public const TYPE_CATEGORY_TILES = 'category_tiles';

    public const TYPE_PRODUCT_GRID = 'product_grid';

    public const TYPE_BENEFITS = 'benefits';

    public const TYPE_RICH_TEXT = 'rich_text';

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_HERO,
            self::TYPE_CATEGORY_TILES,
            self::TYPE_PRODUCT_GRID,
            self::TYPE_BENEFITS,
            self::TYPE_RICH_TEXT,
        ];
    }

    protected $fillable = ['type', 'heading', 'config', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'config' => 'json',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
