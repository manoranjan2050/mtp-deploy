<?php

declare(strict_types=1);

namespace Tests\Feature\Deployments;

use App\Enums\DeploymentStepStatus;
use App\Enums\WebsiteFramework;
use App\Models\Deployment;
use App\Models\Server;
use App\Models\Website;
use App\Services\Deployments\LaravelDeploymentPipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Runs the real pipeline (real `composer`, real PHP interpreter running a
 * fake `artisan` script) against a throwaway "Laravel-shaped" directory - a
 * trivial composer.json (no real dependencies, so `composer install` doesn't
 * need network access) and a stand-in `artisan` that just echoes its command
 * and exits 0, or exits 1 for one deliberately-failing step to prove the
 * pipeline halts on first failure.
 */
class LaravelDeploymentPipelineServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $siteRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siteRoot = sys_get_temp_dir().'/mtp-deploy-pipeline-test-'.uniqid();
        File::ensureDirectoryExists($this->siteRoot);
        File::put($this->siteRoot.'/composer.json', json_encode(['name' => 'test/test']));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->siteRoot);

        parent::tearDown();
    }

    public function test_all_steps_run_in_order_and_succeed(): void
    {
        $this->writeFakeArtisan(shouldFail: false);
        $deployment = $this->makeDeployment();

        $result = app(LaravelDeploymentPipelineService::class)->run($deployment);

        $this->assertTrue($result);

        $steps = $deployment->steps()->get();
        $this->assertCount(7, $steps);
        $this->assertTrue($steps->every(fn ($step) => $step->status === DeploymentStepStatus::Success));
        $this->assertSame(
            ['composer install', 'artisan storage:link', 'artisan config:cache', 'artisan route:cache', 'artisan view:cache', 'artisan migrate', 'artisan queue:restart'],
            $steps->pluck('name')->all()
        );
    }

    public function test_a_failing_step_halts_the_remaining_steps(): void
    {
        $this->writeFakeArtisan(shouldFail: true);
        $deployment = $this->makeDeployment();

        $result = app(LaravelDeploymentPipelineService::class)->run($deployment);

        $this->assertFalse($result);

        $steps = $deployment->steps()->get();

        // composer install succeeds, then artisan storage:link/config:cache/
        // route:cache/view:cache succeed, then migrate fails (the fake
        // artisan is set up to fail on "migrate") and queue:restart never runs.
        $migrateStep = $steps->firstWhere('name', 'artisan migrate');
        $queueStep = $steps->firstWhere('name', 'artisan queue:restart');

        $this->assertSame(DeploymentStepStatus::Failed, $migrateStep->status);
        $this->assertNull($queueStep);
    }

    private function writeFakeArtisan(bool $shouldFail): void
    {
        $failingCommand = $shouldFail ? 'migrate' : '__never__';

        File::put($this->siteRoot.'/artisan', <<<PHP
        <?php
        \$command = \$argv[1] ?? '';
        echo "ran: {\$command}\\n";
        if (\$command === '{$failingCommand}') {
            fwrite(STDERR, "simulated failure\\n");
            exit(1);
        }
        exit(0);
        PHP);
    }

    private function makeDeployment(): Deployment
    {
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);
        $website = Website::query()->create([
            'server_id' => $server->id,
            'name' => 'Example',
            'domain' => 'example-'.uniqid().'.test',
            'document_root' => $this->siteRoot,
            'php_version' => '8.3',
            'framework' => WebsiteFramework::Laravel,
        ]);

        return Deployment::query()->create([
            'website_id' => $website->id,
            'branch' => 'main',
        ]);
    }
}
