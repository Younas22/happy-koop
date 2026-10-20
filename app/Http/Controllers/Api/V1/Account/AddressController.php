<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Account\AddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->data(
            $user->addresses()->orderByDesc('id')->get()
                ->map(static fn (Address $address): array => AddressResource::make($address))
                ->values()
                ->all()
        );
    }

    public function store(AddressRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('create', Address::class);

        $address = $user->addresses()->create($this->attributes($request));

        $this->syncDefaults($address);

        return $this->data(AddressResource::make($address->refresh()), 201);
    }

    public function update(AddressRequest $request, int $id): JsonResponse
    {
        $address = $this->findOwned($request, $id);

        $this->authorize('update', $address);

        $address->fill($this->attributes($request))->save();

        $this->syncDefaults($address);

        return $this->data(AddressResource::make($address->refresh()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $address = $this->findOwned($request, $id);

        $this->authorize('delete', $address);

        $address->delete();

        return response()->json(null, 204);
    }

    public function setDefault(Request $request, int $id): JsonResponse
    {
        $type = (string) $request->input('type');

        if (! in_array($type, ['shipping', 'billing'], true)) {
            throw ApiException::validation([
                'type' => ['The type must be either shipping or billing.'],
            ]);
        }

        $address = $this->findOwned($request, $id);

        $this->authorize('update', $address);

        $column = $type === 'shipping' ? 'is_default_shipping' : 'is_default_billing';

        DB::transaction(function () use ($address, $column): void {
            Address::query()
                ->where('user_id', $address->user_id)
                ->update([$column => false]);

            $address->forceFill([$column => true])->save();
        });

        return $this->data(AddressResource::make($address->refresh()));
    }

    private function findOwned(Request $request, int $id): Address
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Address|null $address */
        $address = Address::query()->where('user_id', $user->id)->find($id);

        // 404 (not 403) for someone else's address: no existence disclosure.
        abort_if($address === null, 404);

        return $address;
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(AddressRequest $request): array
    {
        return [
            'first_name' => trim((string) $request->input('firstName')),
            'last_name' => trim((string) $request->input('lastName')),
            'company' => $request->input('company') ?: null,
            'line1' => trim((string) $request->input('line1')),
            'line2' => $request->input('line2') ?: null,
            'city' => trim((string) $request->input('city')),
            'region' => $request->input('region') ?: null,
            'postal_code' => trim((string) $request->input('postalCode')),
            'country_code' => strtoupper((string) $request->input('countryCode')),
            'phone' => $request->input('phone') ?: null,
            'is_default_shipping' => $request->boolean('isDefaultShipping'),
            'is_default_billing' => $request->boolean('isDefaultBilling'),
        ];
    }

    private function syncDefaults(Address $address): void
    {
        foreach (['is_default_shipping', 'is_default_billing'] as $column) {
            if (! $address->{$column}) {
                continue;
            }

            Address::query()
                ->where('user_id', $address->user_id)
                ->whereKeyNot($address->id)
                ->update([$column => false]);
        }
    }
}
