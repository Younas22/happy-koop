<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An order has entered the delivered state.
 *
 * Dispatched from the status machine - the one place a status transition is
 * authoritative - and only on a real change, never when an administrator saves
 * the same status again.
 */
class OrderDelivered implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Order $order) {}
}
