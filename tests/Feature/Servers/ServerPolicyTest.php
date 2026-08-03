<?php

declare(strict_types=1);

namespace Tests\Feature\Servers;

use App\Models\Server;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    public function test_admin_can_manage_servers(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertTrue($admin->can('viewAny', Server::class));
        $this->assertTrue($admin->can('create', Server::class));
    }

    public function test_developer_cannot_manage_servers(): void
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        $this->assertFalse($developer->can('viewAny', Server::class));
        $this->assertFalse($developer->can('create', Server::class));
    }

    public function test_viewer_cannot_manage_servers(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $this->assertFalse($viewer->can('viewAny', Server::class));
    }

    public function test_developer_cannot_test_a_connection(): void
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        $server = Server::query()->create(['name' => 'Test', 'ssh_host' => 'example.test', 'ssh_user' => 'x', 'ssh_private_key' => 'x']);

        $this->assertFalse($developer->can('testConnection', $server));
    }
}
