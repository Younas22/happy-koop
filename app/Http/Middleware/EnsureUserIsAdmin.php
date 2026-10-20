<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side role check for every /admin route.
 *
 * Applied IN ADDITION to `auth:sanctum` and `abilities:admin`: token abilities
 * are a defence-in-depth layer only, never the authorization decision
 * (api-contract.md 2.2 / 8).
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new ApiException('Authentication is required.', 'UNAUTHENTICATED', 401);
        }

        if (! $user->isAdmin()) {
            throw ApiException::forbidden();
        }

        if ($permission !== null && ! $user->hasPermission($permission)) {
            throw ApiException::forbidden();
        }

        return $next($request);
    }
}
