<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingZone extends Model
{
    protected $fillable = ['name', 'country_codes', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'country_codes' => 'json',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class)->orderBy('sort_order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function coversCountry(string $countryCode): bool
    {
        $codes = array_map('strtoupper', (array) ($this->country_codes ?? []));

        return in_array(strtoupper($countryCode), $codes, true) || in_array('*', $codes, true);
    }
}
