<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Every lookup is scoped by user_id, so another customer's order number
 * resolves to 404 rather than leaking its existence (IDOR).
 */
class AccountOrderController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $paginator = Order::query()
            ->where('user_id', $user->id)
            ->with('items')
            ->orderByDesc('id')
            ->paginate($this->perPage($request, 10, 60));

        return $this->paginated(
            $paginator,
            static fn (Order $order): array => OrderResource::summary($order)
        );
    }

    public function show(Request $request, string $orderNumber): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Order|null $order */
        $order = Order::query()
            ->where('user_id', $user->id)
            ->where('order_number', $orderNumber)
            ->with(['items.product.primaryMedia'])
            ->first();

        abort_if($order === null, 404);

        $this->authorize('view', $order);

        return $this->data(OrderResource::make($order));
    }
}
