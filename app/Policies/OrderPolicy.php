<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OrderPolicy extends AdminPolicy
{
    /**
     * A customer may read their OWN order. Ownership is checked here as well
     * as in the query scope, so an id guessed from another account still
     * cannot be read (IDOR).
     */
    public function view(User $user, Model $model): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $model instanceof Order
            && $model->user_id !== null
            && (int) $model->user_id === (int) $user->id;
    }

    /** Orders are created by the checkout service, never by an admin write. */
    public function create(User $user): bool
    {
        return false;
    }
}
