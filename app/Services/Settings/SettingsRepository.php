<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;

/**
 * Typed, cached access to the `settings` table.
 *
 * Encrypted values are kept as ciphertext in the cache and decrypted only on
 * explicit request, so a cache dump never contains a plaintext secret.
 */
class SettingsRepository
{
    public const CACHE_KEY = 'happykoop.settings.v1';

    /**
     * Raw rows, keyed by setting key: ['value' => ?string, 'type' => string,
     * 'is_public' => bool, 'updated_at' => ?string]
     *
     * @var array<string, array{value: ?string, type: string, is_public: bool, updated_at: ?string}>|null
     */
    private ?array $rows = null;

    /**
     * Typed value for a key, falling back to the registry default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->assertKnown($key);

        $row = $this->rows()[$key] ?? null;

        if ($row === null || $row['value'] === null) {
            return $default ?? SettingsRegistry::default($key);
        }

        $decoded = $this->decode($row['value'], $row['type']);

        return $decoded ?? $default ?? SettingsRegistry::default($key);
    }

    public function getString(string $key, ?string $default = null): ?string
    {
        $value = $this->get($key, $default);

        return $value === null ? null : (string) $value;
    }

    public function getInt(string $key, ?int $default = null): ?int
    {
        $value = $this->get($key, $default);

        return $value === null ? null : (int) $value;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        return (bool) $value;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getArray(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);

        return is_array($value) ? $value : $default;
    }

    /**
     * Decrypted secret. Only ever called from inside a service that needs to
     * talk to the provider — never from a controller or a resource.
     */
    public function getSecret(string $key): ?string
    {
        $this->assertKnown($key);

        if (! SettingsRegistry::isSecret($key)) {
            throw new InvalidArgumentException("Setting [{$key}] is not a secret.");
        }

        $row = $this->rows()[$key] ?? null;

        if ($row === null || $row['value'] === null || $row['value'] === '') {
            return null;
        }

        try {
            $plain = Crypt::decryptString($row['value']);
        } catch (DecryptException) {
            return null;
        }

        return $plain === '' ? null : $plain;
    }

    public function isConfigured(string $key): bool
    {
        $row = $this->rows()[$key] ?? null;

        return $row !== null && $row['value'] !== null && $row['value'] !== '';
    }

    /**
     * Last 4 characters of a secret, for the admin "which key is this" hint.
     * Never returns more than the suffix.
     */
    public function secretSuffix(string $key): ?string
    {
        $plain = $this->getSecret($key);

        if ($plain === null || strlen($plain) < 4) {
            return null;
        }

        return substr($plain, -4);
    }

    public function updatedAt(string $key): ?string
    {
        $row = $this->rows()[$key] ?? null;

        if ($row === null || $row['updated_at'] === null) {
            return null;
        }

        return Carbon::parse($row['updated_at'])->toIso8601String();
    }

    public function set(string $key, mixed $value, ?int $userId = null): void
    {
        $this->assertKnown($key);

        $type = SettingsRegistry::type($key);

        Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $this->encode($value, $type),
                'type' => $type,
                'group' => SettingsRegistry::group($key),
                'is_public' => SettingsRegistry::isPublic($key),
                'updated_by' => $userId,
            ]
        );

        $this->flush();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return list<string> the key names that were written
     */
    public function setMany(array $values, ?int $userId = null): array
    {
        $written = [];

        foreach ($values as $key => $value) {
            $this->set($key, $value, $userId);
            $written[] = $key;
        }

        return $written;
    }

    /**
     * Creates a row only when the key has never been stored. Used by the
     * idempotent seeder, so re-seeding never overwrites an admin's choice.
     */
    public function setDefault(string $key, mixed $value): void
    {
        $this->assertKnown($key);

        if (Setting::query()->where('key', $key)->exists()) {
            return;
        }

        $this->set($key, $value);
    }

    /**
     * All non-secret settings as typed values, keyed by setting key.
     *
     * @return array<string, mixed>
     */
    public function allNonSecret(): array
    {
        $out = [];

        foreach (SettingsRegistry::keys() as $key) {
            if (SettingsRegistry::isSecret($key)) {
                continue;
            }

            $out[$key] = $this->get($key);
        }

        return $out;
    }

    public function flush(): void
    {
        $this->rows = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, array{value: ?string, type: string, is_public: bool, updated_at: ?string}>
     */
    private function rows(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $this->rows = Cache::rememberForever(self::CACHE_KEY, static function (): array {
            $out = [];

            foreach (Setting::query()->get(['key', 'value', 'type', 'is_public', 'updated_at']) as $setting) {
                $out[$setting->key] = [
                    'value' => $setting->value,
                    'type' => $setting->type,
                    'is_public' => (bool) $setting->is_public,
                    'updated_at' => $setting->updated_at?->toIso8601String(),
                ];
            }

            return $out;
        });

        return $this->rows;
    }

    private function assertKnown(string $key): void
    {
        if (! SettingsRegistry::has($key)) {
            throw new InvalidArgumentException("Unknown setting key [{$key}].");
        }
    }

    private function decode(string $raw, string $type): mixed
    {
        return match ($type) {
            Setting::TYPE_INT => (int) $raw,
            Setting::TYPE_BOOL => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            Setting::TYPE_JSON => json_decode($raw, true) ?? null,
            // A secret is never decoded here. Use getSecret() explicitly.
            Setting::TYPE_ENCRYPTED => null,
            default => $raw,
        };
    }

    private function encode(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            Setting::TYPE_INT => (string) (int) $value,
            Setting::TYPE_BOOL => $value ? '1' : '0',
            Setting::TYPE_JSON => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            Setting::TYPE_ENCRYPTED => Crypt::encryptString((string) $value),
            default => (string) $value,
        };
    }
}
