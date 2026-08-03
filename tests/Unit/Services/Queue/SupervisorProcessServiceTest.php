<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Queue;

use App\Enums\WebsiteFramework;
use App\Models\QueueWorker;
use App\Models\Server;
use App\Models\Website;
use App\Services\Queue\SupervisorProcessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SupervisorProcessServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $configPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configPath = sys_get_temp_dir().'/mtp-supervisor-test-'.uniqid();
        config(['mtp.supervisor_config_path' => $this->configPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->configPath);

        parent::tearDown();
    }

    public function test_it_writes_and_removes_a_real_config_file(): void
    {
        $worker = $this->worker();
        $service = app(SupervisorProcessService::class);

        $service->writeConfig($worker);
        $this->assertFileExists("{$this->configPath}/{$worker->supervisor_program_name}.conf");

        $service->removeConfig($worker);
        $this->assertFileDoesNotExist("{$this->configPath}/{$worker->supervisor_program_name}.conf");
    }

    public function test_reload_honestly_reports_failure_when_no_supervisorctl_binary_exists(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('This assertion is specific to environments without a supervisorctl binary.');
        }

        $result = app(SupervisorProcessService::class)->reloadSupervisor();

        $this->assertFalse($result->successful);
    }

    private function worker(): QueueWorker
    {
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);
        $website = Website::query()->create([
            'server_id' => $server->id,
            'name' => 'Example',
            'domain' => 'example.test',
            'document_root' => '/var/www/example.test',
            'php_version' => '8.3',
            'framework' => WebsiteFramework::Laravel,
        ]);

        return QueueWorker::query()->create(['website_id' => $website->id]);
    }
}
