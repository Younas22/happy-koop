<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;

/**
 * The sanitized viewer. Never contains the password hash or the remember
 * token. `isAdmin` is a UI affordance only and is never trusted for
 * authorization (api-contract.md 2.2).
 */
final class UserResource
{
    /**
     * @return array<string, mixed>
     */
    public static function make(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'emailVerifiedAt' => $user->email_verified_at?->toIso8601String(),
            'isAdmin' => $user->isAdmin(),
            'avatarUrl' => $user->avatar?->url(),
            'createdAt' => $user->created_at?->toIso8601String(),
        ];
    }

    /**
     * Account profile (api-contract.md 7).
     *
     * @return array<string, mixed>
     */
    public static function profile(User $user): array
    {
        return self::make($user) + [
            'phone' => $user->phone,
            'acceptsMarketing' => (bool) $user->accepts_marketing,
        ];
    }

    /**
     * Admin customer listing. No password hash, no payment data.
     *
     * @return array<string, mixed>
     */
    public static function adminSummary(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'emailVerifiedAt' => $user->email_verified_at?->toIso8601String(),
            'acceptsMarketing' => (bool) $user->accepts_marketing,
            'isAdmin' => $user->isAdmin(),
            'avatarUrl' => $user->avatar?->url(),
            'roles' => $user->roles->map(static fn ($role): array => [
                'id' => (int) $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
            ])->values()->all(),
            'ordersCount' => (int) ($user->orders_count ?? 0),
            'lastLoginAt' => $user->last_login_at?->toIso8601String(),
            'createdAt' => $user->created_at?->toIso8601String(),
        ];
    }
}
