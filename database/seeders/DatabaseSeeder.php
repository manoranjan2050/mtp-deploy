<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            $admin = User::factory()->create([
                'name' => 'Super Admin',
                'email' => 'admin@mtpdeploy.test',
            ]);
            $admin->assignRole('super-admin');
        }
    }
}
