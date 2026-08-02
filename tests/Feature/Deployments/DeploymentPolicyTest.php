<?php

declare(strict_types=1);

namespace Tests\Feature\Deployments;

use App\Enums\WebsiteFramework;
use App\Models\Deployment;
use App\Models\Server;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeploymentPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $this->server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);
    }

    public function test_developer_can_deploy_their_own_website(): void
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        $website = $this->makeWebsite(['created_by' => $developer->id]);
        $deployment = $this->makeDeployment($website);

        $this->assertTrue($developer->can('deploy', $deployment));
        $this->assertTrue($developer->can('rollback', $deployment));
    }

    public function test_developer_cannot_deploy_another_developers_website(): void
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        $otherDeveloper = User::factory()->create();
        $website = $this->makeWebsite(['created_by' => $otherDeveloper->id]);
        $deployment = $this->makeDeployment($website);

        $this->assertFalse($developer->can('deploy', $deployment));
        $this->assertFalse($developer->can('rollback', $deployment));
    }

    public function test_viewer_cannot_deploy(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $website = $this->makeWebsite();
        $deployment = $this->makeDeployment($website);

        $this->assertFalse($viewer->can('deploy', $deployment));
    }

    public function test_admin_can_deploy_any_website(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $website = $this->makeWebsite();
        $deployment = $this->makeDeployment($website);

        $this->assertTrue($admin->can('deploy', $deployment));
        $this->assertTrue($admin->can('rollback', $deployment));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeWebsite(array $overrides = []): Website
    {
        return Website::query()->create(array_merge([
            'server_id' => $this->server->id,
            'name' => 'Example',
            'domain' => 'example-'.uniqid().'.test',
            'document_root' => '/var/www/example',
            'php_version' => '8.3',
            'framework' => WebsiteFramework::Laravel,
        ], $overrides));
    }

    private function makeDeployment(Website $website): Deployment
    {
        return Deployment::query()->create([
            'website_id' => $website->id,
            'branch' => 'main',
        ]);
    }
}
