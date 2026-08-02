<?php

declare(strict_types=1);

namespace Tests\Feature\Websites;

use App\Enums\WebsiteFramework;
use App\Models\Server;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsitePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    public function test_developer_can_manage_a_website_they_created(): void
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        $website = $this->makeWebsite(['created_by' => $developer->id]);

        $this->assertTrue($developer->can('view', $website));
        $this->assertTrue($developer->can('update', $website));
    }

    public function test_developer_cannot_manage_another_developers_website(): void
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        $otherDeveloper = User::factory()->create();
        $website = $this->makeWebsite(['created_by' => $otherDeveloper->id]);

        $this->assertFalse($developer->can('view', $website));
        $this->assertFalse($developer->can('update', $website));
    }

    public function test_developer_cannot_delete_any_website(): void
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        $website = $this->makeWebsite(['created_by' => $developer->id]);

        $this->assertFalse($developer->can('delete', $website));
    }

    public function test_viewer_can_view_but_not_update_websites(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $website = $this->makeWebsite();

        $this->assertTrue($viewer->can('view', $website));
        $this->assertFalse($viewer->can('update', $website));
    }

    public function test_admin_can_manage_any_website(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $website = $this->makeWebsite();

        $this->assertTrue($admin->can('update', $website));
        $this->assertTrue($admin->can('delete', $website));
    }

    public function test_only_admin_and_super_admin_can_manage_website_services(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $developer = User::factory()->create();
        $developer->assignRole('developer');

        $website = $this->makeWebsite();

        $this->assertTrue($admin->can('manageServices', $website));
        $this->assertFalse($developer->can('manageServices', $website));
    }

    public function test_developer_can_manage_files_on_their_own_website_but_not_anothers(): void
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        $ownWebsite = $this->makeWebsite(['created_by' => $developer->id]);
        $othersWebsite = $this->makeWebsite(['domain' => 'other.example.com']);

        $this->assertTrue($developer->can('manageFiles', $ownWebsite));
        $this->assertFalse($developer->can('manageFiles', $othersWebsite));
    }

    public function test_viewer_cannot_manage_files(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $website = $this->makeWebsite();

        $this->assertFalse($viewer->can('manageFiles', $website));
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
            'domain' => 'example.com',
            'aliases' => [],
            'document_root' => '/var/www/example.com',
            'php_version' => '8.3',
            'framework' => WebsiteFramework::Laravel,
        ], $overrides));
    }
}
