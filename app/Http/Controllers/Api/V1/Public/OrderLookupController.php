<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\OrderResource;
use App\Services\Orders\OrderAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderLookupController extends ApiController
{
    public function __construct(private readonly OrderAccessService $access) {}

    /**
     * The authoritative order status for the checkout return pages. Payment
     * state is never inferred from redirect query parameters.
     */
    public function show(Request $request, string $orderNumber): JsonResponse
    {
        $this->validateQuery($request, [
            'token' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        $order = $this->access->find(
            $orderNumber,
            $request->query('token') === null ? null : (string) $request->query('token'),
            $request->user()
        );

        abort_if($order === null, 404);

        return $this->data(OrderResource::status($order));
    }
}
