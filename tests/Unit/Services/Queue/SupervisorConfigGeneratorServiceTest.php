<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Queue;

use App\Enums\WebsiteFramework;
use App\Models\QueueWorker;
use App\Models\Server;
use App\Models\Website;
use App\Services\Queue\SupervisorConfigGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorConfigGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_a_working_supervisor_program_block(): void
    {
        $website = $this->website();
        $worker = QueueWorker::query()->create([
            'website_id' => $website->id,
            'connection' => 'redis',
            'queue' => 'emails',
            'processes' => 3,
        ]);

        $config = app(SupervisorConfigGeneratorService::class)->generate($worker);

        $this->assertStringContainsString("[program:{$worker->supervisor_program_name}]", $config);
        $this->assertStringContainsString('command=php artisan queue:work redis --queue=emails', $config);
        $this->assertStringContainsString("directory={$website->document_root}", $config);
        $this->assertStringContainsString('numprocs=3', $config);
        $this->assertStringContainsString('autorestart=true', $config);
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
