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
            'view websites',
            'create websites',
            'update websites',
            'delete websites',
            'manage website services',
            'manage website files',
            'view databases',
            'create databases',
            'delete databases',
            'manage database privileges',
            'use terminal',
            'manage cloudflare tunnels',
            'manage cron jobs',
            'view application logs',
            'manage monitoring alerts',
        ]);

        // Developer manages websites they created (scoped in WebsitePolicy, not
        // here - "assigned sites" per docs/Security.md is simplified to
        // "created_by = self" for now; a real per-site assignment pivot table
        // is a reasonable future refinement, not needed yet). No user
        // management, and no "manage website services" - restarting nginx/PHP
        // affects every site on the server, too broad a blast radius for a
        // per-site role. Similarly, a developer can create/delete databases for
        // their own sites but cannot grant/revoke privileges - that's a wider
        // security lever (a misconfigured GRANT can expose one tenant's data to
        // another's DB user) reserved for admin/super-admin.
        $developer = Role::findOrCreate('developer', 'web');
        $developer->syncPermissions([
            'view websites',
            'create websites',
            'update websites',
            'manage website files',
            'view databases',
            'create databases',
            'delete databases',
        ]);

        $viewer = Role::findOrCreate('viewer', 'web');
        $viewer->syncPermissions([
            'view websites',
            'view databases',
        ]);
    }
}
