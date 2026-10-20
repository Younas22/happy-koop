<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates the first administrator from the environment.
 *
 * Credentials are NEVER hardcoded in version control: set SEED_ADMIN_EMAIL
 * and SEED_ADMIN_PASSWORD before seeding. Without them this seeder prints
 * what to do and makes no user.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('happykoop.seed.admin_email');
        $password = config('happykoop.seed.admin_password');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            $this->command?->warn('No admin seeded: set SEED_ADMIN_EMAIL and SEED_ADMIN_PASSWORD in .env, then re-run.');

            return;
        }

        if (strlen($password) < 12) {
            $this->command?->warn('No admin seeded: SEED_ADMIN_PASSWORD must be at least 12 characters.');

            return;
        }

        $email = strtolower(trim($email));
        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            // An existing account is never overwritten, and its password is
            // never reset by a re-run.
            $this->attachAdminRole($existing);
            $this->command?->info("Admin role confirmed for existing user {$email}.");

            return;
        }

        $user = User::query()->create([
            'name' => (string) config('happykoop.seed.admin_name', 'Store Administrator'),
            'email' => $email,
            'password' => $password,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        $this->attachAdminRole($user);

        $this->command?->info("Administrator created: {$email}");
        $this->command?->line('  Password: the SEED_ADMIN_PASSWORD value from your environment. Change it after first sign-in.');
    }

    private function attachAdminRole(User $user): void
    {
        $roleIds = Role::query()->whereIn('slug', [Role::ADMIN, Role::CUSTOMER])->pluck('id')->all();

        $user->roles()->syncWithoutDetaching($roleIds);
    }
}
