<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;

/**
 * Issues Sanctum personal access tokens with the contract's abilities and
 * lifetimes (api-contract.md 2.2 / 2.3): customer 30 days, admin 12 hours.
 */
class TokenIssuer
{
    /**
     * @return array{token: string, expiresAt: string, abilities: list<string>}
     */
    public function issue(User $user, ?string $deviceName = null): array
    {
        $abilities = $user->tokenAbilities();

        $minutes = in_array('admin', $abilities, true)
            ? (int) config('happykoop.tokens.admin_minutes', 720)
            : (int) config('happykoop.tokens.customer_minutes', 43200);

        $expiresAt = Carbon::now()->addMinutes($minutes);

        /** @var NewAccessToken $token */
        $token = $user->createToken(
            $this->sanitizeDeviceName($deviceName),
            $abilities,
            $expiresAt
        );

        return [
            'token' => $token->plainTextToken,
            'expiresAt' => $expiresAt->toIso8601String(),
            'abilities' => $abilities,
        ];
    }

    private function sanitizeDeviceName(?string $deviceName): string
    {
        $name = trim((string) $deviceName);

        if ($name === '') {
            return 'web';
        }

        return Str::limit(preg_replace('/[^\w\s.\-]/u', '', $name) ?: 'web', 60, '');
    }
}
