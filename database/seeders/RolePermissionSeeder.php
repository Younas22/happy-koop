<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const PERMISSIONS = [
        'catalog.manage' => 'Manage products and categories',
        'orders.manage' => 'Manage orders and fulfilment',
        'orders.refund' => 'Issue refunds',
        'customers.view' => 'View customers',
        'reviews.moderate' => 'Moderate reviews',
        'content.manage' => 'Manage pages and homepage',
        'media.manage' => 'Manage media',
        'settings.manage' => 'Manage store settings',
        'payments.manage' => 'Manage payment provider settings',
        'payments.manage_live' => 'Switch a payment provider into live mode',
        'users.manage' => 'Manage administrators and customers',
        'audit.view' => 'Read the audit log',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $slug => $name) {
            Permission::query()->updateOrCreate(['slug' => $slug], ['name' => $name]);
        }

        $admin = Role::query()->updateOrCreate(
            ['slug' => Role::ADMIN],
            ['name' => 'Administrator', 'description' => 'Full access to the admin panel.']
        );

        Role::query()->updateOrCreate(
            ['slug' => Role::CUSTOMER],
            ['name' => 'Customer', 'description' => 'Storefront customer account.']
        );

        // The admin role holds every permission, including live payment mode.
        $admin->permissions()->syncWithoutDetaching(
            Permission::query()->pluck('id')->all()
        );
    }
}
