<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\Media;
use App\Models\Order;
use App\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\Log;

/**
 * Everything the transactional emails need to look like this store.
 *
 * One place resolves the store name, the logo, the brand colour, the storefront
 * origin and the administrator recipient, so no mailable has to reach into
 * settings itself and no template can drift into a different brand.
 *
 * Two rules worth stating:
 *
 *  - The From address is NOT the store's contact email. Resend only accepts a
 *    sender on a verified domain, so the envelope sender stays the configured
 *    `MAIL_FROM_ADDRESS`; the admin's contact address is used for Reply-To and
 *    as the notification recipient instead.
 *  - `adminRecipient()` may be null. An unconfigured store silently dropping
 *    the administrator's copy would be worse than a logged warning, so the
 *    caller logs and skips rather than throwing away a visitor's message.
 */
class MailContext
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function storeName(): string
    {
        $name = trim((string) $this->settings->getString('store.name', ''));

        return $name !== '' ? $name : (string) config('mail.from.name', 'HappyKoop');
    }

    public function fromAddress(): string
    {
        return (string) config('mail.from.address');
    }

    public function fromName(): string
    {
        return $this->storeName();
    }

    /**
     * Where administrator notifications go.
     *
     * The store's configured contact email, falling back to the configured
     * sender - which at least reaches whoever owns the mail domain.
     */
    public function adminRecipient(): ?string
    {
        $configured = trim((string) $this->settings->getString('store.contact_email', ''));

        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            return $configured;
        }

        $fallback = trim((string) config('mail.from.address', ''));

        if ($fallback !== '' && filter_var($fallback, FILTER_VALIDATE_EMAIL)) {
            Log::info('mail.admin_recipient.fallback', [
                'reason' => 'store.contact_email is not set or not a valid address',
            ]);

            return $fallback;
        }

        return null;
    }

    /** The address a customer should reply to, when the store has published one. */
    public function replyTo(): ?string
    {
        $configured = trim((string) $this->settings->getString('store.contact_email', ''));

        return $configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL)
            ? $configured
            : null;
    }

    public function supportPhone(): ?string
    {
        $phone = trim((string) $this->settings->getString('store.contact_phone', ''));

        return $phone !== '' ? $phone : null;
    }

    /** The public storefront origin, without a trailing slash. */
    public function storefrontUrl(): ?string
    {
        $url = trim((string) $this->settings->getString('urls.canonical_frontend_url', ''));

        return $url !== '' ? rtrim($url, '/') : null;
    }

    /**
     * A customer's link to their own order.
     *
     * Orders carry an `access_token` precisely so a guest can reach one without
     * an account, and the storefront looks an order up by number. Without a
     * configured storefront URL there is no link to give, and the email says
     * nothing rather than pointing at a guess.
     */
    public function customerOrderUrl(Order $order): ?string
    {
        $base = $this->storefrontUrl();

        return $base === null
            ? null
            : $base.'/account/orders/'.rawurlencode((string) $order->order_number);
    }

    /** The administrator's link to the order inside the admin panel. */
    public function adminOrderUrl(Order $order): ?string
    {
        $base = $this->storefrontUrl();

        return $base === null ? null : $base.'/admin/orders/'.(int) $order->id;
    }

    public function brandColor(): string
    {
        $hex = (string) $this->settings->getString('theme.primary_color', '#2563EB');

        return preg_match('/^#[0-9a-fA-F]{6}$/', $hex) === 1 ? $hex : '#2563EB';
    }

    /**
     * An absolute logo URL for the email header, when one is configured.
     *
     * Email clients cannot resolve a relative path, and many will not load a
     * remote image at all - every template therefore keeps the store name as
     * text and treats the logo as an enhancement.
     */
    public function logoUrl(): ?string
    {
        $id = $this->settings->getInt('store.logo_media_id');

        if ($id === null) {
            return null;
        }

        $media = Media::query()->find($id);
        $url = $media?->url();

        return is_string($url) && str_starts_with($url, 'http') ? $url : null;
    }

    public function currency(): string
    {
        return (string) $this->settings->getString('locale.currency', 'USD');
    }

    public function locale(): string
    {
        return (string) $this->settings->getString('locale.locale', 'en-US');
    }

    /**
     * The shared view data every email template expects.
     *
     * @return array<string, mixed>
     */
    public function branding(): array
    {
        return [
            'storeName' => $this->storeName(),
            'storeUrl' => $this->storefrontUrl(),
            'logoUrl' => $this->logoUrl(),
            'brandColor' => $this->brandColor(),
            'supportEmail' => $this->replyTo(),
            'supportPhone' => $this->supportPhone(),
        ];
    }
}
