<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Checkout\CartValidateRequest;
use App\Services\Cart\CartValidator;
use Illuminate\Http\JsonResponse;

class CartController extends ApiController
{
    public function __construct(private readonly CartValidator $cart) {}

    /**
     * Server-side cart validation. Works for guests; prices are never taken
     * from the request (api-contract.md 5).
     */
    public function validateCart(CartValidateRequest $request): JsonResponse
    {
        $resolved = $this->cart->resolve(
            (array) $request->input('items', []),
            $request->input('couponCode'),
            $request->user()
        );

        return $this->data($this->cart->toResponse($resolved));
    }
}
