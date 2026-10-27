<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Services\Mail\MailSettingsService;
use App\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsStoreFixtures;
use Tests\TestCase;

/**
 * The email sender (Resend key, From address, From name) is managed from the
 * admin panel and overrides the environment without touching .env.
 */
class MailSettingsTest extends TestCase
{
    use BuildsStoreFixtures, RefreshDatabase;

    private const KEY = 're_test_1234567890abcdWXYZ';

    public function test_the_api_key_is_stored_encrypted_and_never_returned(): void
    {
        Sanctum::actingAs($this->adminUser(), ['customer', 'admin']);

        $response = $this->putJson('/api/v1/admin/settings/mail', [
            'resendApiKey' => self::KEY,
            'fromAddress' => 'contact@happykoop.be',
            'fromName' => 'HappyKoop',
        ])
            ->assertOk()
            ->assertJsonPath('data.resendApiKey.configured', true)
            ->assertJsonPath('data.resendApiKey.maskedSuffix', 'WXYZ')
            ->assertJsonPath('data.fromAddress', 'contact@happykoop.be')
            ->assertJsonPath('data.effective.mailer', 'resend')
            ->assertJsonPath('data.effective.source', 'admin');

        $this->assertStringNotContainsString(self::KEY, (string) $response->getContent());
        $this->assertStringNotContainsString(self::KEY, (string) $this->getJson('/api/v1/admin/settings/mail')->getContent());
        $this->assertDatabaseMissing('settings', ['key' => 'mail.resend_api_key', 'value' => self::KEY]);
    }

    public function test_stored_settings_override_the_environment(): void
    {
        $this->seedStore();
        $settings = app(SettingsRepository::class);
        $settings->set('mail.resend_api_key', self::KEY);
        $settings->set('mail.from_address', 'contact@happykoop.be');
        $settings->set('mail.from_name', 'HappyKoop');

        config(['mail.default' => 'log', 'mail.from.address' => 'env@example.test']);

        app(MailSettingsService::class)->apply();

        $this->assertSame('resend', config('mail.default'));
        $this->assertSame(self::KEY, config('services.resend.key'));
        $this->assertSame('contact@happykoop.be', config('mail.from.address'));
        $this->assertSame('HappyKoop', config('mail.from.name'));
    }

    public function test_without_a_stored_key_the_environment_is_kept(): void
    {
        $this->seedStore();
        config(['mail.default' => 'log']);

        app(MailSettingsService::class)->apply();

        $this->assertSame('log', config('mail.default'));
    }

    public function test_an_empty_key_field_leaves_the_key_unchanged_and_clear_removes_it(): void
    {
        Sanctum::actingAs($this->adminUser(), ['customer', 'admin']);
        $this->putJson('/api/v1/admin/settings/mail', ['resendApiKey' => self::KEY])->assertOk();

        $this->putJson('/api/v1/admin/settings/mail', ['resendApiKey' => '', 'fromName' => 'Shop'])
            ->assertOk()
            ->assertJsonPath('data.resendApiKey.configured', true);

        $this->putJson('/api/v1/admin/settings/mail', ['resendApiKey' => null])
            ->assertOk()
            ->assertJsonPath('data.resendApiKey.configured', true);

        $this->putJson('/api/v1/admin/settings/mail', ['clearResendApiKey' => true])
            ->assertOk()
            ->assertJsonPath('data.resendApiKey.configured', false);
    }

    public function test_a_key_that_is_not_a_resend_key_is_rejected_without_echoing_it(): void
    {
        Sanctum::actingAs($this->adminUser(), ['customer', 'admin']);

        $response = $this->putJson('/api/v1/admin/settings/mail', ['resendApiKey' => 'sk_live_secret_value'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('resendApiKey');

        $this->assertStringNotContainsString('sk_live_secret_value', (string) $response->getContent());
    }

    public function test_a_test_email_is_sent_and_recorded(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->adminUser(), ['customer', 'admin']);

        $this->postJson('/api/v1/admin/settings/mail/test', ['to' => 'owner@example.test'])
            ->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.to', 'owner@example.test');

        $this->getJson('/api/v1/admin/settings/mail')->assertJsonPath('data.lastTest.to', 'owner@example.test');
    }

    public function test_a_customer_cannot_read_or_change_mail_settings(): void
    {
        $this->seedStore();
        Sanctum::actingAs($this->customerUser(), ['customer']);

        $this->getJson('/api/v1/admin/settings/mail')->assertForbidden();
        $this->putJson('/api/v1/admin/settings/mail', ['fromName' => 'x'])->assertForbidden();
    }
}
