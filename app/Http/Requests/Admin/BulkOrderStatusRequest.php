<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Order;

class BulkOrderStatusRequest extends BulkIdsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'status' => ['required', 'string', 'in:'.implode(',', [
                Order::STATUS_PENDING, Order::STATUS_PROCESSING, Order::STATUS_SHIPPED,
                Order::STATUS_DELIVERED, Order::STATUS_CANCELLED, Order::STATUS_REFUNDED,
            ])],
        ];
    }
}
