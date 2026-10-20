<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Order items are immutable snapshots; they are read, never written. */
class OrderItemPolicy extends AdminPolicy
{
    public function view(User $user, Model $model): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $model instanceof OrderItem
            && $model->order !== null
            && (int) $model->order->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $model): bool
    {
        return false;
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
