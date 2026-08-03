<?php

declare(strict_types=1);

namespace Tests\Feature\Queue;

use App\Enums\WebsiteFramework;
use App\Filament\Resources\Websites\Pages\ManageQueueWorkers;
use App\Models\QueueWorker;
use App\Models\Server;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class ManageQueueWorkersPageTest extends TestCase
{
    use RefreshDatabase;

    private string $configPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $this->configPath = sys_get_temp_dir().'/mtp-queue-page-test-'.uniqid();
        config(['mtp.supervisor_config_path' => $this->configPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->configPath);

        parent::tearDown();
    }

    public function test_an_admin_can_create_and_delete_a_queue_worker(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $component = Livewire::actingAs($admin)
            ->test(ManageQueueWorkers::class, ['record' => $this->website()->getKey()])
            ->assertSuccessful()
            ->set('connection', 'redis')
            ->set('queue', 'emails')
            ->set('processes', 2)
            ->call('createWorker')
            ->assertSee('redis');

        $this->assertDatabaseHas('queue_workers', ['connection' => 'redis', 'queue' => 'emails', 'processes' => 2]);

        $workerId = QueueWorker::query()->firstOrFail()->id;

        $component->call('deleteWorker', $workerId);
        $this->assertDatabaseMissing('queue_workers', ['id' => $workerId]);
    }

    public function test_a_viewer_cannot_access_the_page(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        Livewire::actingAs($viewer)
            ->test(ManageQueueWorkers::class, ['record' => $this->website()->getKey()])
            ->assertForbidden();
    }

    private function website(): Website
    {
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);

        return Website::query()->create([
            'server_id' => $server->id,
            'name' => 'Example',
            'domain' => 'example.test',
            'document_root' => '/var/www/example.test',
            'php_version' => '8.3',
            'framework' => WebsiteFramework::Laravel,
        ]);
    }
}
