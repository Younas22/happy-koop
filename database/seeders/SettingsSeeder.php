<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Settings\RuntimeConfigService;
use App\Services\Settings\SettingsRegistry;
use App\Services\Settings\SettingsRepository;
use Illuminate\Database\Seeder;

/**
 * Writes the registry defaults for any setting that has never been stored.
 * An administrator's saved value is never overwritten.
 *
 * No payment credential is seeded: providers stay disabled until an admin
 * enters real keys in the panel.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        /** @var SettingsRepository $settings */
        $settings = app(SettingsRepository::class);

        foreach (SettingsRegistry::defaults() as $key => $default) {
            if (SettingsRegistry::isSecret($key)) {
                continue;
            }

            $settings->setDefault($key, $default);
        }

        // Local development conveniences, only if nothing is stored yet.
        $settings->setDefault('store.name', 'HappyKoop');
        $settings->setDefault('store.tagline', 'Everyday things, thoughtfully chosen.');
        $settings->setDefault('store.contact_email', 'support@happykoop.test');
        $settings->setDefault('store.address_lines', []);
        $settings->setDefault('seo.default_title', 'HappyKoop');
        $settings->setDefault('seo.title_template', '%s | HappyKoop');
        $settings->setDefault(
            'urls.api_base_url',
            rtrim((string) config('app.url'), '/').'/api/v1'
        );
        $settings->setDefault(
            'urls.canonical_frontend_url',
            rtrim((string) config('happykoop.frontend_url'), '/')
        );
        $settings->setDefault('navigation.primary', [
            ['label' => 'Shop', 'href' => '/shop'],
            ['label' => 'About', 'href' => '/pages/about'],
            ['label' => 'Contact', 'href' => '/pages/contact'],
        ]);
        $settings->setDefault('navigation.footer', [
            [
                'heading' => 'Help',
                'links' => [
                    ['label' => 'Shipping', 'href' => '/pages/shipping'],
                    ['label' => 'Returns', 'href' => '/pages/returns'],
                    ['label' => 'Contact', 'href' => '/pages/contact'],
                ],
            ],
            [
                'heading' => 'Company',
                'links' => [
                    ['label' => 'About', 'href' => '/pages/about'],
                    ['label' => 'Privacy', 'href' => '/pages/privacy'],
                    ['label' => 'Terms', 'href' => '/pages/terms'],
                ],
            ],
        ]);

        app(RuntimeConfigService::class)->bumpVersion();

        $this->command?->info('Store settings defaults ensured (existing values untouched).');
    }
}
