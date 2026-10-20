<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\ShippingOptionResource;
use App\Services\Catalog\CatalogContext;
use App\Services\Shipping\ShippingService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingController extends ApiController
{
    public function __construct(
        private readonly ShippingService $shipping,
        private readonly CatalogContext $context,
    ) {}

    /**
     * Returns [] for a destination with no configured rates. The frontend then
     * blocks checkout rather than assuming free shipping.
     */
    public function options(Request $request): JsonResponse
    {
        $this->validateQuery($request, [
            'country_code' => ['required', 'string', 'size:2', 'alpha'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'subtotal_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        // `subtotal_amount` is advisory, used only for free-shipping
        // thresholds. The real subtotal is recomputed at checkout.
        $subtotal = Money::of(
            (int) $request->query('subtotal_amount', '0'),
            $this->context->currency()
        );

        $options = $this->shipping->optionsFor(
            strtoupper((string) $request->query('country_code')),
            $subtotal
        );

        return $this->data(
            $options->map(static fn (array $option): array => ShippingOptionResource::make(
                $option['rate'],
                $option['charge']
            ))->values()->all()
        );
    }
}
