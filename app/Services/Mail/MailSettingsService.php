<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Settings\SettingsRepository;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Email sender settings managed from the admin panel.
 *
 * The Resend API key, From address and From name are stored as settings (the
 * key encrypted, like the payment secrets) and applied over the MAIL_* and
 * RESEND_API_KEY environment values at runtime. The .env file is never
 * written: a web request rewriting it would be a remote code path into the
 * server's configuration, and a cached config would ignore it anyway.
 *
 * When no key is stored, the environment configuration is left as it is.
 */
class MailSettingsService
{
    public const KEY_PREFIX = 're_';

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Admin-facing status. Contains no secret in any field.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $configured = $this->settings->isConfigured('mail.resend_api_key');

        return [
            'resendApiKey' => [
                'configured' => $configured,
                'maskedSuffix' => $configured ? $this->settings->secretSuffix('mail.resend_api_key') : null,
                'updatedAt' => $configured ? $this->settings->updatedAt('mail.resend_api_key') : null,
            ],
            'fromAddress' => $this->settings->getString('mail.from_address'),
            'fromName' => $this->settings->getString('mail.from_name'),
            // What is actually in use right now, wherever it came from.
            'effective' => [
                'mailer' => (string) config('mail.default'),
                'fromAddress' => (string) config('mail.from.address'),
                'fromName' => (string) config('mail.from.name'),
                'source' => $configured ? 'admin' : 'environment',
            ],
            'lastTest' => $this->settings->getArray('mail.last_test') ?: null,
        ];
    }

    /**
     * An omitted or empty API key leaves the stored key unchanged (the form
     * never shows it, so an empty field means "keep it"); `clearResendApiKey`
     * removes it. The From fields are set as given, empty clearing them.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed> the fresh status payload
     */
    public function update(array $input, User $actor): array
    {
        $changed = [];

        if (($input['clearResendApiKey'] ?? false) === true) {
            $this->settings->set('mail.resend_api_key', null, $actor->id);
            $changed[] = 'mail.resend_api_key';
        } elseif (array_key_exists('resendApiKey', $input)) {
            $value = $input['resendApiKey'];

            if ($value !== null && trim((string) $value) !== '') {
                $value = trim((string) $value);

                if (! str_starts_with($value, self::KEY_PREFIX)) {
                    // Never echo the submitted value.
                    throw ApiException::validation([
                        'resendApiKey' => ['This does not look like a Resend API key (expected a re_… key).'],
                    ]);
                }

                $this->settings->set('mail.resend_api_key', $value, $actor->id);
                $changed[] = 'mail.resend_api_key';
            }
        }

        foreach (['fromAddress' => 'mail.from_address', 'fromName' => 'mail.from_name'] as $inputKey => $settingKey) {
            if (! array_key_exists($inputKey, $input)) {
                continue;
            }

            $value = $input[$inputKey];
            $value = $value === null || trim((string) $value) === '' ? null : trim((string) $value);

            $this->settings->set($settingKey, $value, $actor->id);
            $changed[] = $settingKey;
        }

        if ($changed !== []) {
            $this->audit->log('settings.mail.updated', $actor->id, null, $changed);
        }

        $this->apply();

        return $this->status();
    }

    /**
     * Applies the stored settings to the running application's mail config.
     * Safe to call repeatedly; does nothing it does not have a value for.
     */
    public function apply(): void
    {
        $key = $this->settings->getSecret('mail.resend_api_key');
        $fromAddress = trim((string) $this->settings->getString('mail.from_address', ''));
        $fromName = trim((string) $this->settings->getString('mail.from_name', ''));

        if ($key !== null) {
            config([
                'mail.default' => 'resend',
                'services.resend.key' => $key,
            ]);
        }

        if ($fromAddress !== '') {
            config(['mail.from.address' => $fromAddress]);
        }

        if ($fromName !== '') {
            config(['mail.from.name' => $fromName]);
        }

        // Mailers are built once and cached; a changed key must rebuild them.
        if (app()->resolved('mail.manager')) {
            /** @var MailManager $manager */
            $manager = app('mail.manager');
            $manager->forgetMailers();
        }
    }

    /**
     * Sends a test email right away (not queued), so the result is known
     * before the admin leaves the page.
     *
     * @return array{ok: bool, checkedAt: string, message: string, to: string}
     */
    public function sendTest(string $to, User $actor): array
    {
        $this->apply();

        $storeName = (string) config('mail.from.name');

        try {
            Mail::raw(
                "This is a test email from {$storeName}.\n\nIf you can read this, the email settings in the admin panel work.",
                static function ($message) use ($to, $storeName): void {
                    $message->to($to)->subject("Test email from {$storeName}");
                }
            );

            $result = [
                'ok' => true,
                'message' => 'The test email was accepted by '.(string) config('mail.default').'.',
            ];
        } catch (Throwable $e) {
            $result = ['ok' => false, 'message' => $this->sanitize($e->getMessage())];
        }

        $payload = $result + ['checkedAt' => Carbon::now()->toIso8601String(), 'to' => $to];

        $this->settings->set('mail.last_test', $payload, $actor->id);
        $this->audit->log('settings.mail.tested', $actor->id, null, ['mail.last_test']);

        return $payload;
    }

    /** Provider errors are shown to the admin; a key must never be part of one. */
    private function sanitize(string $message): string
    {
        $message = preg_replace('/re_[A-Za-z0-9_]+/', 're_…', $message) ?? '';

        return mb_substr(trim($message) !== '' ? trim($message) : 'The email could not be sent.', 0, 300);
    }
}
