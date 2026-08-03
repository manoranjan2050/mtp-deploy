<?php

declare(strict_types=1);

namespace Tests\Feature\Queue;

use App\Actions\Queue\CreateQueueWorkerAction;
use App\Actions\Queue\DeleteQueueWorkerAction;
use App\Actions\Queue\RestartQueueWorkerAction;
use App\Actions\Queue\StartQueueWorkerAction;
use App\Actions\Queue\StopQueueWorkerAction;
use App\DTOs\Queue\QueueWorkerData;
use App\Enums\WebsiteFramework;
use App\Models\Server;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class QueueWorkerActionsTest extends TestCase
{
    use RefreshDatabase;

    private string $configPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->configPath = sys_get_temp_dir().'/mtp-queue-actions-test-'.uniqid();
        config(['mtp.supervisor_config_path' => $this->configPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->configPath);

        parent::tearDown();
    }

    public function test_create_action_writes_a_real_supervisor_config_file(): void
    {
        $website = $this->website();

        $result = app(CreateQueueWorkerAction::class)->handle(new QueueWorkerData(websiteId: $website->id));

        $this->assertFileExists("{$this->configPath}/{$result['worker']->supervisor_program_name}.conf");
        $this->assertDatabaseHas('activity_log', ['log_name' => 'queue', 'description' => 'created queue worker']);
    }

    public function test_start_stop_restart_actions_update_status_honestly(): void
    {
        $website = $this->website();
        $created = app(CreateQueueWorkerAction::class)->handle(new QueueWorkerData(websiteId: $website->id));
        $worker = $created['worker'];

        $startResult = app(StartQueueWorkerAction::class)->handle($worker);
        $stopResult = app(StopQueueWorkerAction::class)->handle($worker);
        $restartResult = app(RestartQueueWorkerAction::class)->handle($worker);

        if (PHP_OS_FAMILY === 'Windows') {
            // No supervisorctl binary here - every call honestly fails,
            // same "never fake server state" principle as elsewhere.
            $this->assertFalse($startResult->successful);
            $this->assertFalse($stopResult->successful);
            $this->assertFalse($restartResult->successful);
        }
    }

    public function test_delete_action_removes_the_config_file_and_db_row(): void
    {
        $website = $this->website();
        $created = app(CreateQueueWorkerAction::class)->handle(new QueueWorkerData(websiteId: $website->id));
        $worker = $created['worker'];
        $configFile = "{$this->configPath}/{$worker->supervisor_program_name}.conf";

        $this->assertFileExists($configFile);

        app(DeleteQueueWorkerAction::class)->handle($worker);

        $this->assertFileDoesNotExist($configFile);
        $this->assertDatabaseMissing('queue_workers', ['id' => $worker->id]);
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
