<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\PaymentSettingsRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Payments\PayPalGateway;
use App\Services\Payments\StripeGateway;
use App\Services\Settings\PaymentSettingsService;
use App\Services\Settings\RuntimeConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentSettingsController extends ApiController
{
    public function __construct(
        private readonly PaymentSettingsService $payments,
        private readonly RuntimeConfigService $runtimeConfig,
        private readonly StripeGateway $stripe,
        private readonly PayPalGateway $paypal,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Status only — never a secret, in any field (api-contract.md 8.2).
     */
    public function show(): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        return $this->data($this->payments->status());
    }

    public function update(PaymentSettingsRequest $request): JsonResponse
    {
        $this->authorize('update', new Setting);

        /** @var User $actor */
        $actor = $request->user();

        $status = $this->payments->update($request->validated(), $actor);

        // Provider enablement is part of the public runtime config.
        $this->runtimeConfig->bumpVersion($actor->id);

        return $this->data($status);
    }

    /**
     * Real server-side connection check. The returned message is sanitized
     * and never contains a credential.
     */
    public function test(Request $request): JsonResponse
    {
        $this->authorize('update', new Setting);

        /** @var User $actor */
        $actor = $request->user();

        $provider = (string) $request->input('provider');

        if (! in_array($provider, PaymentSettingsService::PROVIDERS, true)) {
            throw ApiException::validation([
                'provider' => ['The provider must be either stripe or paypal.'],
            ]);
        }

        $result = $provider === 'stripe'
            ? $this->stripe->testConnection()
            : $this->paypal->testConnection();

        $recorded = $this->payments->recordCheck($provider, $result['ok'], $result['message']);

        $this->audit->log(
            'settings.payments.tested',
            $actor->id,
            null,
            ["payments.{$provider}.last_check"],
            $request
        );

        return $this->data($recorded);
    }
}
