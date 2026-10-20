<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces every /api/* request to be treated as a JSON request.
 *
 * Without this, a client that omits `Accept: application/json` sends Laravel
 * down its browser-oriented paths. The clearest symptom: an unauthenticated
 * request hits the guest-redirect branch, which resolves route('login') - a
 * route an API-only application does not define - so the caller receives a
 * 500 instead of the 401 the contract promises (api-contract.md 1.4).
 *
 * Setting the header here, before the route's own middleware runs, means the
 * contract's error envelope applies regardless of what the caller sent.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
