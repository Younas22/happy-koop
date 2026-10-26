<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Settings\SettingsRepository;
use App\Support\MoneyFormatter;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Throwable;

class HappyKoopServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsRepository::class);

        /*
         * Store display locale for MoneyFormatter. Resolved lazily and
         * defensively: money formatting must never fail because the settings
         * table is unavailable (migrations, unit tests, early boot).
         */
        $this->app->bind(MoneyFormatter::LOCALE_BINDING, function ($app): string {
            try {
                return $app->make(SettingsRepository::class)
                    ->getString('locale.locale', MoneyFormatter::DEFAULT_LOCALE)
                    ?? MoneyFormatter::DEFAULT_LOCALE;
            } catch (Throwable) {
                return MoneyFormatter::DEFAULT_LOCALE;
            }
        });
    }

    public function boot(): void
    {
        $this->registerRateLimiters();
        $this->registerNotificationUrls();
    }

    /**
     * Password-reset and email-verification links must land on the Next.js
     * storefront, not on the API. The frontend then calls the API with the
     * signed parameters it received.
     */
    private function registerNotificationUrls(): void
    {
        $frontend = rtrim((string) config('happykoop.frontend_url'), '/');

        ResetPassword::createUrlUsing(static function (object $notifiable, string $token) use ($frontend): string {
            return $frontend.'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });

        VerifyEmail::createUrlUsing(static function (object $notifiable) use ($frontend): string {
            $signed = URL::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addMinutes((int) config('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );

            $query = parse_url($signed, PHP_URL_QUERY) ?: '';

            return $frontend.'/verify-email?'.http_build_query([
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]).($query === '' ? '' : '&'.$query);
        });
    }

    /**
     * Named limiters matching the throttle column of api-contract.md.
     */
    private function registerRateLimiters(): void
    {
        $perIp = static fn (int $max) => static fn (Request $request) => Limit::perMinute($max)->by($request->ip());
        $perUser = static fn (int $max) => static fn (Request $request) => Limit::perMinute($max)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip());

        // --- auth -----------------------------------------------------------
        RateLimiter::for('auth-register', $perIp(5));
        RateLimiter::for('auth-login', static function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by('login-ip:'.$request->ip()),
                Limit::perMinute(5)->by('login-id:'.$request->ip().'|'.$email),
            ];
        });
        RateLimiter::for('auth-logout', $perUser(30));
        RateLimiter::for('auth-logout-all', $perUser(10));
        RateLimiter::for('auth-me', $perUser(60));
        RateLimiter::for('auth-forgot-password', $perIp(3));
        RateLimiter::for('auth-reset-password', $perIp(5));
        RateLimiter::for('auth-email-verify', $perIp(6));
        RateLimiter::for('auth-email-resend', $perUser(2));

        // --- public ---------------------------------------------------------
        RateLimiter::for('public-read', $perIp(120));
        RateLimiter::for('cart-validate', $perIp(60));
        // A human sends one message, not five a minute.
        RateLimiter::for('contact-submit', $perIp(5));
        RateLimiter::for('checkout-initialize', $perIp(20));
        RateLimiter::for('payments', $perIp(20));
        RateLimiter::for('reviews-submit', static fn (Request $request) => Limit::perHour(3)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        // --- authenticated areas -------------------------------------------
        RateLimiter::for('account', $perUser(120));
        RateLimiter::for('admin', $perUser(240));
        RateLimiter::for('admin-settings', $perUser(20));
        // The media library uploads a bulk selection one file per request.
        RateLimiter::for('admin-media-upload', $perUser(120));

        // --- webhooks -------------------------------------------------------
        // Generous: providers retry aggressively, and every event is
        // de-duplicated by its unique event id anyway.
        RateLimiter::for('webhooks', static fn (Request $request) => Limit::perMinute(600)->by($request->ip()));
    }
}
