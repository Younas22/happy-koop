<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;
use App\Models\Order;

class OrderStatusRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', 'in:'.implode(',', [
                Order::STATUS_PENDING, Order::STATUS_PROCESSING, Order::STATUS_SHIPPED,
                Order::STATUS_DELIVERED, Order::STATUS_CANCELLED, Order::STATUS_REFUNDED,
            ])],
            'paymentStatus' => ['sometimes', 'string', 'in:'.implode(',', [
                Order::PAYMENT_UNPAID, Order::PAYMENT_AUTHORIZED, Order::PAYMENT_PAID,
                Order::PAYMENT_PARTIALLY_REFUNDED, Order::PAYMENT_REFUNDED,
                Order::PAYMENT_FAILED, Order::PAYMENT_CANCELLED,
            ])],
            'adminNote' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
