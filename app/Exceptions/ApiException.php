<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Domain error carrying the contract's machine-readable `code` plus an HTTP
 * status. Rendered by the handler in bootstrap/app.php into the single error
 * envelope defined in api-contract.md 1.3.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>|null  $errors
     */
    public function __construct(
        string $message,
        public readonly string $errorCode = 'BAD_REQUEST',
        public readonly int $status = 400,
        public readonly ?array $errors = null,
        public readonly array $headers = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function badRequest(string $message): self
    {
        return new self($message, 'BAD_REQUEST', 400);
    }

    public static function forbidden(string $message = 'This action is unauthorized.'): self
    {
        return new self($message, 'FORBIDDEN', 403);
    }

    public static function notFound(string $message = 'Resource not found.'): self
    {
        return new self($message, 'NOT_FOUND', 404);
    }

    public static function conflict(string $message): self
    {
        return new self($message, 'CONFLICT', 409);
    }

    public static function gone(string $message): self
    {
        return new self($message, 'GONE', 410);
    }

    /**
     * @param  array<string, list<string>>|null  $errors
     */
    public static function cartChanged(
        string $message = 'Your cart changed. Please review it before continuing.',
        ?array $errors = null,
    ): self {
        return new self($message, 'CART_CHANGED', 422, $errors);
    }

    public static function paymentNotConfigured(
        string $message = 'This payment method is not available right now.'
    ): self {
        return new self($message, 'PAYMENT_NOT_CONFIGURED', 422);
    }

    public static function liveModeLocked(
        string $message = 'Live payment mode must be explicitly confirmed by an authorized administrator.'
    ): self {
        return new self($message, 'LIVE_MODE_LOCKED', 423);
    }

    public static function serviceUnavailable(
        string $message = 'The payment provider is unavailable. Please try again shortly.'
    ): self {
        return new self($message, 'SERVICE_UNAVAILABLE', 503);
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function validation(array $errors, string $message = 'The given data was invalid.'): self
    {
        return new self($message, 'VALIDATION_FAILED', 422, $errors);
    }
}
