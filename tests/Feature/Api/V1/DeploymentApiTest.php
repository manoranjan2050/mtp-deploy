<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\WebsiteFramework;
use App\Models\Server;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Reuses the same "real local bare git repo as a stand-in for GitHub" fixture
 * pattern as GitDeploymentServiceTest, driven through the actual HTTP API
 * this time instead of calling the service directly.
 */
class DeploymentApiTest extends TestCase
{
    use RefreshDatabase;

    private string $tempRoot;

    private string $remotePath;

    private string $documentRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $this->tempRoot = sys_get_temp_dir().'/mtp-deploy-api-test-'.uniqid();
        $this->remotePath = $this->tempRoot.'/remote.git';
        $workingClone = $this->tempRoot.'/working-clone';
        $this->documentRoot = $this->tempRoot.'/deployed-site';

        File::ensureDirectoryExists($this->tempRoot);

        $this->runGit(['git', 'init', '--bare', '--initial-branch=main', $this->remotePath], $this->tempRoot);
        $this->runGit(['git', 'clone', $this->remotePath, $workingClone], $this->tempRoot);
        $this->runGit(['git', 'config', 'user.email', 'test@example.com'], $workingClone);
        $this->runGit(['git', 'config', 'user.name', 'Test'], $workingClone);

        File::put($workingClone.'/index.php', '<?php echo "v1";');
        $this->runGit(['git', 'add', '.'], $workingClone);
        $this->runGit(['git', 'commit', '-m', 'first commit'], $workingClone);
        $this->runGit(['git', 'push', 'origin', 'main'], $workingClone);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempRoot);

        parent::tearDown();
    }

    public function test_it_triggers_a_deployment(): void
    {
        $user = $this->admin();
        $website = $this->website();
        Sanctum::actingAs($user, ['deployments:write']);

        $response = $this->postJson("/api/v1/websites/{$website->id}/deployments");

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'success');
        $response->assertJsonPath('data.triggered_by', 'api');
    }

    public function test_it_cannot_trigger_a_deployment_without_deployments_write(): void
    {
        $user = $this->admin();
        $website = $this->website();
        Sanctum::actingAs($user, ['deployments:read']);

        $this->postJson("/api/v1/websites/{$website->id}/deployments")->assertForbidden();
    }

    public function test_it_lists_deployments_for_a_website(): void
    {
        $user = $this->admin();
        $website = $this->website();
        Sanctum::actingAs($user, ['deployments:write']);

        $this->postJson("/api/v1/websites/{$website->id}/deployments")->assertCreated();

        Sanctum::actingAs($user, ['deployments:read']);
        $response = $this->getJson("/api/v1/websites/{$website->id}/deployments");

        $response->assertSuccessful();
        $response->assertJsonCount(1, 'data');
    }

    public function test_it_rolls_back_a_deployment(): void
    {
        $user = $this->admin();
        $website = $this->website();
        Sanctum::actingAs($user, ['deployments:write']);

        $first = $this->postJson("/api/v1/websites/{$website->id}/deployments")->json('data');

        $response = $this->postJson("/api/v1/deployments/{$first['id']}/rollback");

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'rolled_back');
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function website(): Website
    {
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);

        return Website::query()->create([
            'server_id' => $server->id,
            'name' => 'Example',
            'domain' => 'deploy-api-'.uniqid().'.test',
            'document_root' => $this->documentRoot,
            'php_version' => '8.3',
            'framework' => WebsiteFramework::PlainPhp,
            'repository_url' => $this->remotePath,
            'git_branch' => 'main',
        ]);
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
