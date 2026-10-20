<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderAccessService;

/**
 * Finds the order a payment request refers to and refuses to start a payment
 * for an order that is already settled or expired.
 */
class PaymentOrderResolver
{
    public function __construct(private readonly OrderAccessService $access) {}

    public function payable(string $orderNumber, ?string $accessToken, ?User $user): Order
    {
        $order = $this->access->find($orderNumber, $accessToken, $user);

        if ($order === null) {
            throw ApiException::notFound('Order not found.');
        }

        if ($order->isPaidOrAuthorized()) {
            throw ApiException::conflict('This order has already been paid.');
        }

        if (in_array($order->status, [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED], true)) {
            throw ApiException::conflict('This order can no longer be paid.');
        }

        if ($order->expires_at !== null && $order->expires_at < now()) {
            throw ApiException::gone('This checkout has expired. Please start a new order.');
        }

        return $order;
    }
}
