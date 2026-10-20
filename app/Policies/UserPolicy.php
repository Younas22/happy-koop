<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends AdminPolicy
{
    public function view(User $user, Model $model): bool
    {
        return $user->isAdmin() || (int) $model->getKey() === (int) $user->id;
    }

    /** An administrator can never delete their own account. */
    public function delete(User $user, Model $model): bool
    {
        return $user->isAdmin() && (int) $model->getKey() !== (int) $user->id;
    }

    /**
     * Role assignment is admin-only, and an administrator may not strip their
     * own admin role (which would lock everyone out of the panel).
     */
    public function assignRoles(User $user, Model $model): bool
    {
        return $user->isAdmin();
    }
}
