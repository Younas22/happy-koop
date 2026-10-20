<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Checkout\CheckoutInitializeRequest;
use App\Services\Checkout\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CheckoutController extends ApiController
{
    public function __construct(private readonly CheckoutService $checkout) {}

    public function initialize(CheckoutInitializeRequest $request): JsonResponse
    {
        $key = trim((string) $request->header('Idempotency-Key'));

        if ($key === '' || ! Str::isUuid($key)) {
            throw ApiException::badRequest(
                'A valid Idempotency-Key header (UUIDv4) is required for this request.'
            );
        }

        $result = $this->checkout->initialize(
            $request->validated(),
            $request->user(),
            $key
        );

        return $this->data($result['payload'], $result['status']);
    }
}
