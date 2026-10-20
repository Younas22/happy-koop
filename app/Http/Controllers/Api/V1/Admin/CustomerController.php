<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\AddressResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\UserResource;
use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer lookup. No password hashes and no payment credentials are ever
 * included (api-contract.md 8).
 */
class CustomerController extends ApiController
{
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
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        return $this->paginated(
            $query->paginate($this->perPage($request)),
            static fn (User $user): array => UserResource::adminSummary($user)
        );
    }

    public function show(int $id): JsonResponse
    {
        /** @var User|null $user */
        $user = User::query()->with('roles')->withCount('orders')->find($id);

        abort_if($user === null, 404);

        $this->authorize('view', $user);

        return $this->data(UserResource::adminSummary($user) + [
            'addresses' => $user->addresses()->orderByDesc('id')->get()
                ->map(static fn (Address $address): array => AddressResource::make($address))
                ->values()->all(),
            'orders' => Order::query()
                ->where('user_id', $user->id)
                ->with('items')
                ->orderByDesc('id')
                ->limit(25)
                ->get()
                ->map(static fn (Order $order): array => OrderResource::summary($order))
                ->values()->all(),
        ]);
    }
}
