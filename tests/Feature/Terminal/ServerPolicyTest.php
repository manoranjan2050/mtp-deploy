<?php

declare(strict_types=1);

namespace Tests\Feature\Terminal;

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

    public function test_admin_can_use_the_terminal(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertTrue($admin->can('useTerminal', $this->server()));
    }

    public function test_developer_cannot_use_the_terminal(): void
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        $this->assertFalse($developer->can('useTerminal', $this->server()));
    }

    public function test_viewer_cannot_use_the_terminal(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $this->assertFalse($viewer->can('useTerminal', $this->server()));
    }

    public function test_super_admin_can_use_the_terminal(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $this->assertTrue($superAdmin->can('useTerminal', $this->server()));
    }

    private function server(): Server
    {
        return Server::query()->create(['name' => 'Test Server', 'is_local' => true]);
    }
}
