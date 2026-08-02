<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    public function test_developer_cannot_view_users(): void
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        $this->assertFalse($developer->can('viewAny', User::class));
    }

    public function test_viewer_cannot_manage_roles(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $this->assertFalse($viewer->can('create', Role::class));
    }

    public function test_admin_can_view_and_suspend_users_but_not_delete_them(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $target = User::factory()->create();

        $this->assertTrue($admin->can('viewAny', User::class));
        $this->assertTrue($admin->can('suspend', $target));
        $this->assertFalse($admin->can('delete', $target));
    }

    public function test_super_admin_bypasses_all_permission_checks(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $target = User::factory()->create();

        $this->assertTrue($superAdmin->can('delete', $target));
        $this->assertTrue($superAdmin->can('create', Role::class));
    }

    public function test_admin_cannot_modify_a_super_admin_account(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $this->assertFalse($admin->can('update', $superAdmin));
        $this->assertFalse($admin->can('suspend', $superAdmin));
    }

    public function test_user_cannot_suspend_themselves(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertFalse($admin->can('suspend', $admin));
    }
}
