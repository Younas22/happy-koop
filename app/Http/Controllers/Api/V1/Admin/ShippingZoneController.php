<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\ShippingZoneRequest;
use App\Http\Resources\MoneyResource;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Services\AuditLogger;
use App\Services\Settings\StoreCurrency;
use App\Support\Html;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Shipping zones and their rates (what GET /shipping/options reads).
 *
 * A country may belong to one zone only, and only one zone may be the `*`
 * catch-all: ShippingService picks the first matching zone, so an overlap
 * would silently hide the other zone's rates. Rates always take the store
 * currency; the client never chooses it.
 */
class ShippingZoneController extends ApiController
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly StoreCurrency $currency,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ShippingZone::class);

        $zones = ShippingZone::query()
            ->with('rates')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $this->data($zones->map(fn (ShippingZone $zone): array => $this->present($zone))->values()->all());
    }

    public function store(ShippingZoneRequest $request): JsonResponse
    {
        $this->authorize('create', ShippingZone::class);

        $input = $request->validated();
        $this->assertNoOverlap($input['countryCodes'], null);
        $this->assertRatesValid($input['rates'] ?? []);

        $zone = DB::transaction(function () use ($input): ShippingZone {
            $zone = ShippingZone::query()->create($this->zoneAttributes($input));
            $this->syncRates($zone, $input['rates'] ?? []);

            return $zone;
        });

        $this->audit->log('shipping_zone.created', $request->user()?->id, $zone, array_keys($input), $request);

        return $this->data($this->present($zone->load('rates')), 201);
    }

    public function update(ShippingZoneRequest $request, int $id): JsonResponse
    {
        $zone = $this->find($id);

        $this->authorize('update', $zone);

        $input = $request->validated();

        if (array_key_exists('countryCodes', $input)) {
            $this->assertNoOverlap($input['countryCodes'], $zone);
        }

        if (array_key_exists('rates', $input)) {
            $this->assertRatesValid($input['rates']);
        }

        DB::transaction(function () use ($zone, $input): void {
            $zone->fill($this->zoneAttributes($input))->save();

            if (array_key_exists('rates', $input)) {
                $this->syncRates($zone, $input['rates']);
            }
        });

        $this->audit->log('shipping_zone.updated', $request->user()?->id, $zone, array_keys($input), $request);

        return $this->data($this->present($zone->refresh()->load('rates')));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $zone = $this->find($id);

        $this->authorize('delete', $zone);

        // Rates cascade. Placed orders keep their own copy of the method
        // code and label, so nothing historical is lost.
        $zone->delete();

        $this->audit->log('shipping_zone.deleted', $request->user()?->id, $zone, ['id'], $request);

        return response()->json(null, 204);
    }

    /**
     * @param  list<string>  $codes
     */
    private function assertNoOverlap(array $codes, ?ShippingZone $self): void
    {
        $others = ShippingZone::query()
            ->when($self !== null, fn ($query) => $query->whereKeyNot($self->id))
            ->get(['id', 'name', 'country_codes']);

        foreach ($others as $other) {
            $taken = array_map('strtoupper', (array) ($other->country_codes ?? []));
            $clash = array_values(array_intersect($codes, $taken));

            if ($clash === []) {
                continue;
            }

            $message = $clash === ['*']
                ? "The zone \"{$other->name}\" is already the catch-all (*) zone."
                : 'Already covered by the zone "'.$other->name.'": '.implode(', ', $clash).'. A country can belong to one zone only.';

            throw ApiException::validation(['countryCodes' => [$message]]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rates
     */
    private function assertRatesValid(array $rates): void
    {
        $errors = [];
        $defaults = 0;

        foreach ($rates as $index => $rate) {
            $min = $rate['estimateMinDays'] ?? null;
            $max = $rate['estimateMaxDays'] ?? null;

            if ($min !== null && $max !== null && $max < $min) {
                $errors["rates.{$index}.estimateMaxDays"] = ['The maximum delivery days cannot be less than the minimum.'];
            }

            if ((bool) ($rate['isDefault'] ?? false)) {
                $defaults++;
            }
        }

        if ($defaults > 1) {
            $errors['rates'] = ['Only one rate in a zone can be the default.'];
        }

        if ($errors !== []) {
            throw ApiException::validation($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function zoneAttributes(array $input): array
    {
        $out = [];

        if (array_key_exists('name', $input)) {
            $out['name'] = Html::text($input['name'], 160);
        }

        foreach ([
            'countryCodes' => 'country_codes',
            'isActive' => 'is_active',
            'sortOrder' => 'sort_order',
        ] as $inputKey => $column) {
            if (array_key_exists($inputKey, $input)) {
                $out[$column] = $input[$inputKey];
            }
        }

        return $out;
    }

    /**
     * Replaces the zone's rates with `$rates`, in the given order.
     *
     * @param  list<array<string, mixed>>  $rates
     */
    private function syncRates(ShippingZone $zone, array $rates): void
    {
        $existing = $zone->rates()->get()->keyBy('id');
        $keep = [];

        foreach ($rates as $rate) {
            $id = isset($rate['id']) ? (int) $rate['id'] : null;

            if ($id !== null && $existing->has($id)) {
                $keep[] = $id;
            }
        }

        // Delete first so a removed rate's code can be reused in the same save.
        $zone->rates()->whereNotIn('id', $keep)->delete();

        $currency = $this->currency->code();

        foreach ($rates as $position => $rate) {
            $id = isset($rate['id']) ? (int) $rate['id'] : null;

            $attributes = [
                'code' => $rate['code'],
                'label' => Html::text($rate['label'], 160),
                'description' => isset($rate['description']) ? Html::text($rate['description'], 255) : null,
                'price_amount' => (int) $rate['priceAmount'],
                'currency' => $currency,
                'min_subtotal_amount' => $rate['minSubtotalAmount'] ?? null,
                'free_above_amount' => $rate['freeAboveAmount'] ?? null,
                'estimate_min_days' => $rate['estimateMinDays'] ?? null,
                'estimate_max_days' => $rate['estimateMaxDays'] ?? null,
                'is_default' => (bool) ($rate['isDefault'] ?? false),
                'is_active' => (bool) ($rate['isActive'] ?? true),
                'sort_order' => $position,
            ];

            if ($id !== null && $existing->has($id)) {
                $existing->get($id)->fill($attributes)->save();
            } else {
                $zone->rates()->create($attributes);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ShippingZone $zone): array
    {
        return [
            'id' => (int) $zone->id,
            'name' => $zone->name,
            'countryCodes' => array_values(array_map('strtoupper', (array) ($zone->country_codes ?? []))),
            'isActive' => (bool) $zone->is_active,
            'sortOrder' => (int) $zone->sort_order,
            'rates' => $zone->rates->map(static fn (ShippingRate $rate): array => [
                'id' => (int) $rate->id,
                'code' => $rate->code,
                'label' => $rate->label,
                'description' => $rate->description,
                'price' => MoneyResource::fromMinor($rate->price_amount, $rate->currency),
                'minSubtotal' => MoneyResource::fromNullableMinor($rate->min_subtotal_amount, $rate->currency),
                'freeAbove' => MoneyResource::fromNullableMinor($rate->free_above_amount, $rate->currency),
                'estimateMinDays' => $rate->estimate_min_days,
                'estimateMaxDays' => $rate->estimate_max_days,
                'isDefault' => (bool) $rate->is_default,
                'isActive' => (bool) $rate->is_active,
            ])->values()->all(),
            'updatedAt' => $zone->updated_at?->toIso8601String(),
        ];
    }

    private function find(int $id): ShippingZone
    {
        /** @var ShippingZone|null $zone */
        $zone = ShippingZone::query()->find($id);

        abort_if($zone === null, 404);

        return $zone;
    }
}
