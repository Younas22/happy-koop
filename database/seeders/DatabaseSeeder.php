<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Development seed data.
 *
 * Idempotent by construction: every seeder uses firstOrCreate /
 * updateOrCreate. Nothing is ever truncated or deleted, so re-running is
 * safe against a database that already holds real records.
 *
 * It refuses to run in production.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('Seeding is disabled in production. Aborting.');

            return;
        }

        $this->call([
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
            SettingsSeeder::class,
            ShippingSeeder::class,
            MediaLibrarySeeder::class,
            DemoUserSeeder::class,
            CatalogSeeder::class,
            DrawerDividerCatalogSeeder::class,
            CmsSeeder::class,
        ]);

        $this->command?->newLine();
        $this->command?->info('HappyKoop demo data seeded. No reviews, ratings or discounts were created:');
        $this->command?->line('  fabricated social proof and fake price comparisons are deliberately not seeded.');
    }
}
