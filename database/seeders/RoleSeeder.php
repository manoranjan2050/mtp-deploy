<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // PermissionSeeder just created rows in the same request - the
        // permission registrar's cache must be cleared or syncPermissions()
        // below won't see them yet.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // super-admin is granted every permission via a Gate::before bypass
        // (see AppServiceProvider) - it is never assigned permissions directly
        // so newly added permissions are automatically covered.
        Role::findOrCreate('super-admin', 'web');

        $admin = Role::findOrCreate('admin', 'web');
        $admin->syncPermissions([
            'view users',
            'create users',
            'update users',
            'suspend users',
            'view roles',
            'view activity log',
        ]);

        // developer/viewer hold no Module 1 permissions - they gain
        // resource-scoped permissions starting with Module 3 (Website Manager).
        Role::findOrCreate('developer', 'web');
        Role::findOrCreate('viewer', 'web');
    }
}
