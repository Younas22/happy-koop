<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\TokenIssuer;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    public function __construct(private readonly TokenIssuer $tokens) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::query()->create([
            'name' => $request->string('name')->trim()->value(),
            'email' => strtolower($request->string('email')->trim()->value()),
            'password' => $request->string('password')->value(),
            'phone' => $request->input('phone'),
            'accepts_marketing' => (bool) $request->boolean('acceptsMarketing'),
        ]);

        $customerRole = Role::query()->where('slug', Role::CUSTOMER)->first();

        if ($customerRole !== null) {
            $user->roles()->syncWithoutDetaching([$customerRole->id]);
        }

        event(new Registered($user));

        $user->refresh()->load('roles');

        return $this->data($this->sessionPayload($user, $request->input('deviceName')), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $email = strtolower($request->string('email')->trim()->value());

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        // Identical failure for an unknown email and a wrong password: no
        // account enumeration (api-contract.md 2.5).
        if ($user === null || ! Hash::check($request->string('password')->value(), (string) $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        $user->load('roles');

        return $this->data($this->sessionPayload($user, $request->input('deviceName')));
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token !== null && method_exists($token, 'delete')) {
            $token->delete();
        }

        return $this->ok();
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()?->tokens()->delete();

        return $this->ok();
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->data(['user' => UserResource::profile($user->load('roles'))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionPayload(User $user, ?string $deviceName): array
    {
        $issued = $this->tokens->issue($user, $deviceName);

        return [
            'token' => $issued['token'],
            'expiresAt' => $issued['expiresAt'],
            'abilities' => $issued['abilities'],
            'user' => UserResource::make($user),
        ];
    }
}
