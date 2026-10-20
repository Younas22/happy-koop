<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleCors as HappyKoopHandleCors;
use App\Http\Requests\ApiRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Maps an HTTP status onto the contract's machine-readable code table
 * (api-contract.md 1.4).
 */
$codeForStatus = static function (int $status): string {
    return match ($status) {
        400 => 'BAD_REQUEST',
        401 => 'UNAUTHENTICATED',
        403 => 'FORBIDDEN',
        404 => 'NOT_FOUND',
        405 => 'BAD_REQUEST',
        409 => 'CONFLICT',
        410 => 'GONE',
        422 => 'VALIDATION_FAILED',
        423 => 'LIVE_MODE_LOCKED',
        429 => 'RATE_LIMITED',
        503 => 'SERVICE_UNAVAILABLE',
        default => $status >= 500 ? 'SERVER_ERROR' : 'BAD_REQUEST',
    };
};

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: null,
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Webhook endpoints must never participate in the browser CORS
        // allowlist: payment providers are servers, not browsers.
        $middleware->replace(HandleCors::class, HappyKoopHandleCors::class);

        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'admin' => EnsureUserIsAdmin::class,
        ]);

        // API routes are stateless bearer-token routes; CSRF applies to the
        // web group only, so webhook routes are CSRF-exempt by construction.
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) use ($codeForStatus): void {
        $exceptions->shouldRenderJsonWhen(
            static fn (Request $request, Throwable $e): bool => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (Throwable $e, Request $request) use ($codeForStatus) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            // Domain errors carry their own code/status/headers.
            if ($e instanceof ApiException) {
                $payload = [
                    'message' => $e->getMessage(),
                    'code' => $e->errorCode,
                ];

                // A domain error supplies its own keys already in the shape the
                // caller sent them (camelCase for JSON bodies, snake_case for
                // query-string parameters per api-contract.md 1.1).
                if ($e->errors !== null) {
                    $payload['errors'] = $e->errors;
                }

                return response()->json($payload, $e->status, $e->headers);
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'code' => 'VALIDATION_FAILED',
                    'errors' => ApiRequest::camelCaseKeys($e->errors()),
                ], 422);
            }

            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'message' => 'Authentication is required.',
                    'code' => 'UNAUTHENTICATED',
                ], 401);
            }

            if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
                return response()->json([
                    'message' => 'This action is unauthorized.',
                    'code' => 'FORBIDDEN',
                ], 403);
            }

            if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                return response()->json([
                    'message' => 'Resource not found.',
                    'code' => 'NOT_FOUND',
                ], 404);
            }

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
                $headers = $e->getHeaders();

                if ($status === 429 && ! isset($headers['Retry-After']) && ! isset($headers['retry-after'])) {
                    $headers['Retry-After'] = 60;
                }

                $message = $e->getMessage();

                $defaults = [
                    429 => 'Too many requests. Please slow down and try again.',
                    503 => 'The service is temporarily unavailable.',
                    405 => 'This method is not allowed for this endpoint.',
                    413 => 'The uploaded file is too large.',
                ];

                if ($message === '' || $status >= 500) {
                    $message = $defaults[$status] ?? 'The request could not be completed.';
                }

                return response()->json([
                    'message' => $defaults[$status] ?? $message,
                    'code' => $codeForStatus($status),
                ], $status, $headers);
            }

            // Anything else: never leak the exception message, SQL or trace.
            report($e);

            $payload = [
                'message' => 'An unexpected error occurred. Please try again.',
                'code' => 'SERVER_ERROR',
            ];

            if (config('app.debug')) {
                $payload['debug'] = [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile().':'.$e->getLine(),
                ];
            }

            return response()->json($payload, 500);
        });
    })->create();
