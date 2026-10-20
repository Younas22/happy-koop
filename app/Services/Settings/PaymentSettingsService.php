<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Exceptions\ApiException;
use App\Models\Permission;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Carbon;

/**
 * Reads and writes payment provider configuration.
 *
 * A decrypted secret never leaves this service. Everything the admin UI gets
 * is {configured, maskedSuffix, updatedAt} (api-contract.md 8.2).
 */
class PaymentSettingsService
{
    public const PROVIDERS = ['stripe', 'paypal'];

    /**
     * Provider => [mode => allowed key prefixes]
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const KEY_PREFIXES = [
        'stripe' => [
            'test' => ['secret_key' => 'sk_test_', 'publishable_key' => 'pk_test_'],
            'live' => ['secret_key' => 'sk_live_', 'publishable_key' => 'pk_live_'],
        ],
    ];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly AuditLogger $audit,
    ) {}

    /* --------------------------------------------------------------- read */

    /**
     * The admin-facing status payload. Contains no secret in any field.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return [
            'stripe' => [
                'enabled' => $this->settings->getBool('payments.stripe.enabled'),
                'mode' => $this->settings->getString('payments.stripe.mode', 'test'),
                'publishableKey' => $this->settings->getString('payments.stripe.publishable_key'),
                'secretKey' => $this->secretStatus('payments.stripe.secret_key'),
                'webhookSecret' => $this->secretStatus('payments.stripe.webhook_secret'),
                'webhookUrl' => $this->webhookUrl('stripe'),
                'lastCheck' => $this->settings->getArray('payments.stripe.last_check') ?: null,
            ],
            'paypal' => [
                'enabled' => $this->settings->getBool('payments.paypal.enabled'),
                'mode' => $this->settings->getString('payments.paypal.mode', 'sandbox'),
                'clientId' => $this->settings->getString('payments.paypal.client_id'),
                'clientSecret' => $this->secretStatus('payments.paypal.client_secret'),
                'webhookId' => $this->secretStatus('payments.paypal.webhook_id'),
                'webhookUrl' => $this->webhookUrl('paypal'),
                'lastCheck' => $this->settings->getArray('payments.paypal.last_check') ?: null,
            ],
        ];
    }

    /**
     * @return array{configured: bool, maskedSuffix: ?string, updatedAt: ?string}
     */
    public function secretStatus(string $key): array
    {
        $configured = $this->settings->isConfigured($key);

        return [
            'configured' => $configured,
            'maskedSuffix' => $configured ? $this->settings->secretSuffix($key) : null,
            'updatedAt' => $configured ? $this->settings->updatedAt($key) : null,
        ];
    }

    public function webhookUrl(string $provider): string
    {
        return rtrim((string) config('app.url'), '/')."/api/v1/webhooks/{$provider}";
    }

    public function isEnabled(string $provider): bool
    {
        return $this->settings->getBool("payments.{$provider}.enabled");
    }

    public function mode(string $provider): string
    {
        $default = $provider === 'paypal' ? 'sandbox' : 'test';

        return $this->settings->getString("payments.{$provider}.mode", $default) ?? $default;
    }

    /**
     * Server-side credentials for a gateway call. Never serialized.
     *
     * @return array<string, string|null>
     */
    public function credentials(string $provider): array
    {
        if ($provider === 'stripe') {
            return [
                'mode' => $this->mode('stripe'),
                'publishable_key' => $this->settings->getString('payments.stripe.publishable_key'),
                'secret_key' => $this->settings->getSecret('payments.stripe.secret_key'),
                'webhook_secret' => $this->settings->getSecret('payments.stripe.webhook_secret'),
            ];
        }

        return [
            'mode' => $this->mode('paypal'),
            'client_id' => $this->settings->getString('payments.paypal.client_id'),
            'client_secret' => $this->settings->getSecret('payments.paypal.client_secret'),
            'webhook_id' => $this->settings->getSecret('payments.paypal.webhook_id'),
        ];
    }

    /* -------------------------------------------------------------- write */

    /**
     * Applies a validated PUT payload.
     *
     * - An omitted or empty-string secret field means "leave unchanged".
     * - An explicit null clears the stored secret.
     *
     * @param  array<string, mixed>  $input  already-validated request data
     * @return array<string, mixed> the fresh status payload
     */
    public function update(array $input, User $actor): array
    {
        $changed = [];

        foreach (self::PROVIDERS as $provider) {
            if (! array_key_exists($provider, $input) || ! is_array($input[$provider])) {
                continue;
            }

            $changed = array_merge($changed, $this->updateProvider($provider, $input[$provider], $input, $actor));
        }

        if ($changed !== []) {
            $this->audit->log('settings.payments.updated', $actor->id, null, $changed);
        }

        return $this->status();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $root
     * @return list<string>
     */
    private function updateProvider(string $provider, array $data, array $root, User $actor): array
    {
        $changed = [];
        $prefix = "payments.{$provider}.";

        $currentMode = $this->mode($provider);
        $mode = array_key_exists('mode', $data) ? (string) $data['mode'] : $currentMode;
        $liveMode = in_array($mode, ['live'], true);

        $enabled = array_key_exists('enabled', $data)
            ? (bool) $data['enabled']
            : $this->isEnabled($provider);

        // Live mode must be explicitly confirmed by an authorized admin.
        if ($liveMode && ($mode !== $currentMode || $enabled)) {
            $this->assertLiveModeAllowed($root, $actor);
        }

        if (array_key_exists('mode', $data) && $mode !== $currentMode) {
            $this->settings->set($prefix.'mode', $mode, $actor->id);
            $changed[] = $prefix.'mode';
        }

        // Public identifiers (not secrets) — validated against the mode.
        $publicFields = $provider === 'stripe'
            ? ['publishableKey' => 'publishable_key']
            : ['clientId' => 'client_id'];

        foreach ($publicFields as $inputKey => $settingKey) {
            if (! array_key_exists($inputKey, $data)) {
                continue;
            }

            $value = $data[$inputKey];

            if (is_string($value) && trim($value) === '') {
                continue; // treated as "leave unchanged", like a secret field
            }

            if ($value !== null) {
                $this->assertKeyPrefix($provider, $settingKey, (string) $value, $mode, $inputKey);
            }

            $this->settings->set($prefix.$settingKey, $value === null ? null : (string) $value, $actor->id);
            $changed[] = $prefix.$settingKey;
        }

        // Secrets.
        $secretFields = $provider === 'stripe'
            ? ['secretKey' => 'secret_key', 'webhookSecret' => 'webhook_secret']
            : ['clientSecret' => 'client_secret', 'webhookId' => 'webhook_id'];

        foreach ($secretFields as $inputKey => $settingKey) {
            if (! array_key_exists($inputKey, $data)) {
                continue; // omitted => unchanged
            }

            $value = $data[$inputKey];

            if (is_string($value) && trim($value) === '') {
                continue; // empty string => unchanged
            }

            if ($value === null) {
                $this->settings->set($prefix.$settingKey, null, $actor->id);
                $changed[] = $prefix.$settingKey;

                continue;
            }

            $value = trim((string) $value);

            $this->assertKeyPrefix($provider, $settingKey, $value, $mode, $inputKey);

            $this->settings->set($prefix.$settingKey, $value, $actor->id);
            $changed[] = $prefix.$settingKey;
        }

        if (array_key_exists('enabled', $data) && $enabled !== $this->isEnabled($provider)) {
            $this->settings->set($prefix.'enabled', $enabled, $actor->id);
            $changed[] = $prefix.'enabled';
        }

        return $changed;
    }

    /**
     * @param  array<string, mixed>  $root
     */
    private function assertLiveModeAllowed(array $root, User $actor): void
    {
        $confirmed = (bool) ($root['confirmLiveMode'] ?? false);

        if (! $confirmed || ! $actor->hasPermission(Permission::PAYMENTS_MANAGE_LIVE)) {
            throw ApiException::liveModeLocked();
        }
    }

    /**
     * Validation never echoes the submitted value.
     */
    private function assertKeyPrefix(
        string $provider,
        string $settingKey,
        string $value,
        string $mode,
        string $inputKey,
    ): void {
        $expected = self::KEY_PREFIXES[$provider][$mode][$settingKey] ?? null;

        if ($expected === null) {
            return; // PayPal credentials have no documented prefix scheme.
        }

        if (! str_starts_with($value, $expected)) {
            throw ApiException::validation([
                "{$provider}.{$inputKey}" => [
                    "This key does not match the selected {$mode} mode (expected a {$expected}… key).",
                ],
            ]);
        }
    }

    /**
     * Records the outcome of a server-side connection check. The message is
     * sanitized by the caller.
     *
     * @return array{ok: bool, checkedAt: string, message: string}
     */
    public function recordCheck(string $provider, bool $ok, string $message): array
    {
        $payload = [
            'ok' => $ok,
            'checkedAt' => Carbon::now()->toIso8601String(),
            'message' => $message,
        ];

        $this->settings->set("payments.{$provider}.last_check", $payload);

        return $payload;
    }
}
