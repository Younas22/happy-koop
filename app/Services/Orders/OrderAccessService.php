<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\User;

/**
 * Resolves an order for a guest (opaque per-order access token) or for its
 * owner (bearer).
 *
 * A wrong or missing token yields 404 rather than 403, so order numbers cannot
 * be probed (api-contract.md 5 / decision 8).
 */
class OrderAccessService
{
    public function find(string $orderNumber, ?string $accessToken, ?User $user): ?Order
    {
        /** @var Order|null $order */
        $order = Order::query()
            ->with(['items.product.primaryMedia'])
            ->where('order_number', $orderNumber)
            ->first();

        if ($order === null) {
            return null;
        }

        if ($user !== null && $order->user_id !== null && (int) $order->user_id === (int) $user->id) {
            return $order;
        }

        if ($user !== null && $user->isAdmin()) {
            return $order;
        }

        if ($accessToken !== null
            && $accessToken !== ''
            && hash_equals((string) $order->access_token, $accessToken)) {
            return $order;
        }

        return null;
    }
}
