<?php

declare(strict_types=1);

namespace Tests\Feature\Cron;

use App\Models\Server;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerCronPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    public function test_admin_can_manage_cron_jobs(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertTrue($admin->can('manageCronJobs', $this->server()));
    }

    public function test_developer_cannot_manage_cron_jobs(): void
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        $this->assertFalse($developer->can('manageCronJobs', $this->server()));
    }

    public function test_viewer_cannot_manage_cron_jobs(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $this->assertFalse($viewer->can('manageCronJobs', $this->server()));
    }

    private function server(): Server
    {
        return Server::query()->create(['name' => 'Test Server', 'is_local' => true]);
    }
}
