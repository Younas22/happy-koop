<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AddressPolicy extends AdminPolicy
{
    public function view(User $user, Model $model): bool
    {
        return $this->owns($user, $model) || $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Model $model): bool
    {
        return $this->owns($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->owns($user, $model);
    }

    private function owns(User $user, Model $model): bool
    {
        return $model instanceof Address && (int) $model->user_id === (int) $user->id;
    }
}
