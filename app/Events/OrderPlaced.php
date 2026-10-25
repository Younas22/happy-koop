<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An order has been created and committed.
 *
 * Creation is not payment. This event says a customer reached the end of
 * checkout and an order row exists; whether money has arrived is the order's
 * own `payment_status`, which the emails print as recorded.
 */
class OrderPlaced implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Order $order) {}
}
