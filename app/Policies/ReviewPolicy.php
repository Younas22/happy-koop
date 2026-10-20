<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ReviewPolicy extends AdminPolicy
{
    /** Any authenticated customer may submit a review (moderated). */
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $model): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $model instanceof Review && (int) $model->user_id === (int) $user->id;
    }

    /** Moderation (status / storeReply) is admin-only. */
    public function moderate(User $user): bool
    {
        return $user->isAdmin();
    }
}
