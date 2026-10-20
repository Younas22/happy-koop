<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends ApiController
{
    /**
     * Signed-URL verification. The `signed` middleware has already rejected a
     * tampered or expired link before this runs.
     */
    public function verify(Request $request, string $id, string $hash): JsonResponse
    {
        /** @var User|null $user */
        $user = User::query()->find($id);

        if ($user === null || ! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            throw ApiException::badRequest('This verification link is invalid.');
        }

        if ($user->hasVerifiedEmail()) {
            return $this->data(['ok' => true, 'alreadyVerified' => true]);
        }

        $user->markEmailAsVerified();

        event(new Verified($user));

        return $this->ok();
    }

    public function resend(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->data(['ok' => true, 'alreadyVerified' => true]);
        }

        $user->sendEmailVerificationNotification();

        return $this->ok();
    }
}
