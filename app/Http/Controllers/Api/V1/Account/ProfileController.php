<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileController extends ApiController
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->data(UserResource::profile($user->load('roles')));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $attributes = [];

        if ($request->has('name')) {
            $attributes['name'] = trim((string) $request->input('name'));
        }

        if ($request->has('phone')) {
            $phone = $request->input('phone');
            $attributes['phone'] = is_string($phone) && trim($phone) !== '' ? trim($phone) : null;
        }

        if ($request->has('acceptsMarketing')) {
            $attributes['accepts_marketing'] = $request->boolean('acceptsMarketing');
        }

        // Email changes are deliberately not accepted here: they would require
        // re-verification and an anti-takeover flow.
        $user->fill($attributes)->save();

        return $this->data(UserResource::profile($user->refresh()->load('roles')));
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! Hash::check((string) $request->input('currentPassword'), (string) $user->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => ['The current password is incorrect.'],
            ]);
        }

        $user->forceFill(['password' => (string) $request->input('password')])->save();

        // Every other session is invalidated; the caller keeps its own token.
        $currentId = $user->currentAccessToken()?->getKey();

        $user->tokens()->when(
            $currentId !== null,
            static fn ($query) => $query->whereKeyNot($currentId)
        )->delete();

        return $this->ok();
    }
}
