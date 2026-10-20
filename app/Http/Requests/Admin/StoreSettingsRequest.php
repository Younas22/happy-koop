<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;
use App\Rules\HexColorRule;
use App\Rules\PublicUrlRule;
use App\Rules\TimezoneRule;
use App\Support\CurrencyRegistry;
use App\Support\MoneyFormatter;

class StoreSettingsRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'storeName' => ['sometimes', 'string', 'min:1', 'max:120'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'logoMediaId' => ['sometimes', 'nullable', 'integer', 'exists:media,id'],
            'logoDarkMediaId' => ['sometimes', 'nullable', 'integer', 'exists:media,id'],
            'faviconMediaId' => ['sometimes', 'nullable', 'integer', 'exists:media,id'],

            'primaryColor' => ['sometimes', 'nullable', 'string', new HexColorRule()],
            'accentColor' => ['sometimes', 'nullable', 'string', new HexColorRule()],
            'radius' => ['sometimes', 'string', 'in:none,sm,md,lg,xl'],

            'contactEmail' => ['sometimes', 'nullable', 'string', 'email:rfc', 'max:255'],
            'contactPhone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'addressLines' => ['sometimes', 'nullable', 'array', 'max:6'],
            'addressLines.*' => ['string', 'max:160'],

            'currency' => ['sometimes', 'string', 'in:'.implode(',', CurrencyRegistry::codes())],
            'locale' => ['sometimes', 'string', 'in:'.implode(',', MoneyFormatter::locales())],
            'timezone' => ['sometimes', 'string', new TimezoneRule()],

            'canonicalFrontendUrl' => ['sometimes', 'nullable', 'string', 'max:2048', new PublicUrlRule()],
            'activeApiBaseUrl' => ['sometimes', 'nullable', 'string', 'max:2048', new PublicUrlRule()],

            'seoDefaultTitle' => ['sometimes', 'nullable', 'string', 'max:160'],
            'seoTitleTemplate' => ['sometimes', 'nullable', 'string', 'max:160'],
            'seoDefaultDescription' => ['sometimes', 'nullable', 'string', 'max:320'],
            'seoDefaultOgImageMediaId' => ['sometimes', 'nullable', 'integer', 'exists:media,id'],

            'shippingSummary' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'returnsSummary' => ['sometimes', 'nullable', 'string', 'max:2000'],

            'social' => ['sometimes', 'nullable', 'array'],
            'social.instagram' => ['sometimes', 'nullable', 'string', 'max:2048', new PublicUrlRule()],
            'social.facebook' => ['sometimes', 'nullable', 'string', 'max:2048', new PublicUrlRule()],
            'social.tiktok' => ['sometimes', 'nullable', 'string', 'max:2048', new PublicUrlRule()],
            'social.x' => ['sometimes', 'nullable', 'string', 'max:2048', new PublicUrlRule()],

            'features' => ['sometimes', 'array'],
            'features.reviews' => ['sometimes', 'boolean'],
            'features.wishlist' => ['sometimes', 'boolean'],
            'features.newsletter' => ['sometimes', 'boolean'],
            'features.guestCheckout' => ['sometimes', 'boolean'],
            'features.search' => ['sometimes', 'boolean'],
            'features.publicStockCounts' => ['sometimes', 'boolean'],

            'navigation' => ['sometimes', 'array'],
            'navigation.primary' => ['sometimes', 'array', 'max:12'],
            'navigation.primary.*.label' => ['required', 'string', 'max:60'],
            'navigation.primary.*.href' => ['required', 'string', 'max:255'],
            'navigation.footer' => ['sometimes', 'array', 'max:6'],
            'navigation.footer.*.heading' => ['required', 'string', 'max:60'],
            'navigation.footer.*.links' => ['required', 'array', 'max:12'],
            'navigation.footer.*.links.*.label' => ['required', 'string', 'max:60'],
            'navigation.footer.*.links.*.href' => ['required', 'string', 'max:255'],
        ];
    }
}
