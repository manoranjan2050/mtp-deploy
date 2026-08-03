<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    public const PERMISSIONS = [
        'view users',
        'create users',
        'update users',
        'delete users',
        'suspend users',
        'view roles',
        'create roles',
        'update roles',
        'delete roles',
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
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }
}
