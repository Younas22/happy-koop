<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\AdminUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends ApiController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $this->validateQuery($request, [
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:60'],
        ]);

        $query = User::query()->with('roles')->withCount('orders')->orderByDesc('id');

        if ($term = trim((string) $request->query('q', ''))) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $query->where(static function ($q) use ($like): void {
                $q->where('name', 'like', $like)->orWhere('email', 'like', $like);
            });
        }

        return $this->paginated(
            $query->paginate($this->perPage($request)),
            static fn (User $user): array => UserResource::adminSummary($user)
        );
    }

    public function store(AdminUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = User::query()->create([
            'name' => trim((string) $request->input('name')),
            'email' => strtolower(trim((string) $request->input('email'))),
            'password' => (string) $request->input('password'),
            'phone' => $request->input('phone'),
        ]);

        $this->syncRoles($user, (array) $request->input('roleSlugs', [Role::CUSTOMER]));

        $this->audit->log('user.created', $request->user()?->id, $user, ['email', 'roleSlugs'], $request);

        return $this->data(UserResource::adminSummary($user->refresh()->load('roles')), 201);
    }

    public function show(int $id): JsonResponse
    {
        $user = $this->find($id);

        $this->authorize('view', $user);

        return $this->data(UserResource::adminSummary($user));
    }

    public function update(AdminUserRequest $request, int $id): JsonResponse
    {
        $user = $this->find($id);

        $this->authorize('update', $user);

        /** @var User $actor */
        $actor = $request->user();

        $attributes = [];

        foreach (['name' => 'name', 'phone' => 'phone'] as $input => $column) {
            if ($request->has($input)) {
                $attributes[$column] = $request->input($input);
            }
        }

        if ($request->has('email')) {
            $attributes['email'] = strtolower(trim((string) $request->input('email')));
        }

        if ($request->filled('password')) {
            $attributes['password'] = (string) $request->input('password');
        }

        $user->fill($attributes)->save();

        if ($request->has('roleSlugs')) {
            $slugs = (array) $request->input('roleSlugs');

            // An administrator may not strip their own admin role: that would
            // lock the panel for everyone.
            if ((int) $user->id === (int) $actor->id
                && $user->isAdmin()
                && ! in_array(Role::ADMIN, $slugs, true)) {
                throw ApiException::forbidden('You cannot remove your own administrator role.');
            }

            $this->syncRoles($user, $slugs);
        }

        $this->audit->log('user.updated', $actor->id, $user, array_keys($attributes), $request);

        return $this->data(UserResource::adminSummary($user->refresh()->load('roles')));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $this->find($id);

        /** @var User $actor */
        $actor = $request->user();

        if ((int) $user->id === (int) $actor->id) {
            throw ApiException::forbidden('You cannot delete your own account.');
        }

        $this->authorize('delete', $user);

        $this->audit->log('user.deleted', $actor->id, $user, ['deleted_at'], $request);

        $user->tokens()->delete();
        $user->delete();

        return response()->json(null, 204);
    }

    /**
     * @param  list<string>  $slugs
     */
    private function syncRoles(User $user, array $slugs): void
    {
        $ids = Role::query()->whereIn('slug', $slugs)->pluck('id')->all();

        $user->roles()->sync($ids);
    }

    private function find(int $id): User
    {
        /** @var User|null $user */
        $user = User::query()->with('roles')->withCount('orders')->find($id);

        abort_if($user === null, 404);

        return $user;
    }
}
