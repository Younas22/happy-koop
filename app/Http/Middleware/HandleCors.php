<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\HandleCors as BaseHandleCors;
use Illuminate\Http\Request;

/**
 * CORS, with payment webhooks explicitly excluded.
 *
 * Providers call those endpoints server-to-server; they are not browsers and
 * must never appear in the browser origin allowlist (api-contract.md 10).
 */
class HandleCors extends BaseHandleCors
{
    /**
     * @var list<string>
     */
    public const EXCLUDED = [
        'api/v1/webhooks/*',
    ];

    public function handle($request, Closure $next)
    {
        if ($this->isExcluded($request)) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }

    protected function isExcluded(Request $request): bool
    {
        foreach (self::EXCLUDED as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }
}
