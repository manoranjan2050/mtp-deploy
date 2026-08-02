<?php

declare(strict_types=1);

namespace Tests\Feature\Deployments;

use App\Actions\Deployments\RollbackDeploymentAction;
use App\Enums\DeploymentStatus;
use App\Enums\DeploymentTrigger;
use App\Enums\WebsiteFramework;
use App\Models\Server;
use App\Models\Website;
use App\Services\Deployments\GitDeploymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Exercises real `git` commands end to end - a local bare repository fixture
 * stands in for "GitHub", so the whole clone -> deploy -> rollback cycle runs
 * against actual git, not a mock, without needing network access.
 */
class GitDeploymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $tempRoot;

    private string $remotePath;

    private string $workingClonePath;

    private string $documentRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempRoot = sys_get_temp_dir().'/mtp-deploy-git-test-'.uniqid();
        $this->remotePath = $this->tempRoot.'/remote.git';
        $this->workingClonePath = $this->tempRoot.'/working-clone';
        $this->documentRoot = $this->tempRoot.'/deployed-site';

        File::ensureDirectoryExists($this->tempRoot);

        $this->runGit(['git', 'init', '--bare', '--initial-branch=main', $this->remotePath], $this->tempRoot);
        $this->runGit(['git', 'clone', $this->remotePath, $this->workingClonePath], $this->tempRoot);
        $this->runGit(['git', 'config', 'user.email', 'test@example.com'], $this->workingClonePath);
        $this->runGit(['git', 'config', 'user.name', 'Test'], $this->workingClonePath);

        $this->commitFile('index.php', '<?php echo "v1";', 'first commit');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempRoot);

        parent::tearDown();
    }

    public function test_first_deploy_clones_the_repository_and_records_the_commit(): void
    {
        $website = $this->makeWebsite();

        $deployment = app(GitDeploymentService::class)->deploy($website, DeploymentTrigger::Manual);

        $this->assertSame(DeploymentStatus::Success, $deployment->status);
        $this->assertNotEmpty($deployment->commit_sha);
        $this->assertFileExists($this->documentRoot.'/index.php');
        $this->assertStringContainsString('v1', File::get($this->documentRoot.'/index.php'));
    }

    public function test_second_deploy_pulls_the_latest_commit(): void
    {
        $website = $this->makeWebsite();
        $service = app(GitDeploymentService::class);

        $first = $service->deploy($website, DeploymentTrigger::Manual);

        $this->commitFile('index.php', '<?php echo "v2";', 'second commit');

        $second = $service->deploy($website, DeploymentTrigger::Manual);

        $this->assertSame(DeploymentStatus::Success, $second->status);
        $this->assertNotSame($first->commit_sha, $second->commit_sha);
        $this->assertStringContainsString('v2', File::get($this->documentRoot.'/index.php'));
    }

    public function test_rollback_restores_a_previous_commits_content(): void
    {
        $website = $this->makeWebsite();
        $service = app(GitDeploymentService::class);

        $first = $service->deploy($website, DeploymentTrigger::Manual);

        $this->commitFile('index.php', '<?php echo "v2";', 'second commit');
        $service->deploy($website, DeploymentTrigger::Manual);

        $this->assertStringContainsString('v2', File::get($this->documentRoot.'/index.php'));

        $rollback = app(RollbackDeploymentAction::class)->handle($first);

        $this->assertSame(DeploymentStatus::RolledBack, $rollback->status);
        $this->assertSame($first->commit_sha, $rollback->commit_sha);
        $this->assertStringContainsString('v1', File::get($this->documentRoot.'/index.php'));
    }

    public function test_a_failed_deploy_is_recorded_with_the_error_output(): void
    {
        $website = $this->makeWebsite(['repository_url' => $this->tempRoot.'/does-not-exist']);

        $deployment = app(GitDeploymentService::class)->deploy($website, DeploymentTrigger::Manual);

        $this->assertSame(DeploymentStatus::Failed, $deployment->status);
        $this->assertNotEmpty($deployment->log);
    }

    private function commitFile(string $filename, string $contents, string $message): void
    {
        File::put($this->workingClonePath.'/'.$filename, $contents);
        $this->runGit(['git', 'add', '.'], $this->workingClonePath);
        $this->runGit(['git', 'commit', '-m', $message], $this->workingClonePath);
        $this->runGit(['git', 'push', 'origin', 'main'], $this->workingClonePath);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeWebsite(array $overrides = []): Website
    {
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);

        return Website::query()->create(array_merge([
            'server_id' => $server->id,
            'name' => 'Example',
            'domain' => 'example-'.uniqid().'.test',
            'document_root' => $this->documentRoot,
            'php_version' => '8.3',
            'framework' => WebsiteFramework::Laravel,
            'repository_url' => $this->remotePath,
            'git_branch' => 'main',
        ], $overrides));
    }

    /**
     * @param  list<string>  $command
     */
    private function runGit(array $command, string $cwd): void
    {
        $process = new Process($command, $cwd);
        $process->mustRun();
    }
}
