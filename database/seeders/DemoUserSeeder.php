<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DemoUserSeeder skipped in production.');

            return;
        }

        $admin = User::query()
            ->where('email', strtolower(trim((string) config('happykoop.seed.admin_email'))))
            ->first();

        if ($admin !== null && $admin->avatar_media_id === null) {
            $avatar = MediaLibrarySeeder::find('person-thumbs-up.png');

            if ($avatar !== null) {
                $admin->forceFill(['avatar_media_id' => $avatar->id])->save();
            }
        }

        $customer = User::query()->firstOrNew(['email' => 'demo.customer@happykoop.test']);
        $created = ! $customer->exists;
        $password = null;

        if ($created) {
            $password = Str::random(40);
            $customer->fill([
                'name' => 'Demo Customer',
                'password' => $password,
            ]);
            $customer->forceFill(['email_verified_at' => now()])->save();
        }

        $customer->roles()->syncWithoutDetaching(
            Role::query()->where('slug', Role::CUSTOMER)->pluck('id')->all()
        );

        if ($customer->avatar_media_id === null) {
            $avatar = MediaLibrarySeeder::find('person-pointing.png');

            if ($avatar !== null) {
                $customer->forceFill(['avatar_media_id' => $avatar->id])->save();
            }
        }

        if ($created) {
            $this->command?->info('Demo customer created: demo.customer@happykoop.test');
            $this->command?->line("  Password (shown once): {$password}");
        } else {
            $this->command?->info('Demo customer account confirmed; its password was not changed.');
        }
    }
}
