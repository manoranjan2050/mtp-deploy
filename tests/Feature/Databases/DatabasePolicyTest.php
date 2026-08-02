<?php

declare(strict_types=1);

namespace Tests\Feature\Databases;

use App\Enums\WebsiteFramework;
use App\Models\Database;
use App\Models\DatabaseUser;
use App\Models\Server;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabasePolicyTest extends TestCase
{
    use RefreshDatabase;

    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $this->server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);
    }

    public function test_developer_can_view_a_database_on_their_own_website(): void
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        $website = $this->makeWebsite(['created_by' => $developer->id]);
        $database = $this->makeDatabase(['website_id' => $website->id]);

        $this->assertTrue($developer->can('view', $database));
        $this->assertTrue($developer->can('delete', $database));
    }

    public function test_developer_cannot_view_a_database_on_another_developers_website(): void
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        $otherDeveloper = User::factory()->create();
        $website = $this->makeWebsite(['created_by' => $otherDeveloper->id]);
        $database = $this->makeDatabase(['website_id' => $website->id]);

        $this->assertFalse($developer->can('view', $database));
        $this->assertFalse($developer->can('delete', $database));
    }

    public function test_developer_cannot_manage_database_privileges(): void
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        $database = $this->makeDatabase();

        $this->assertFalse($developer->can('managePrivileges', $database));
    }

    public function test_admin_can_manage_privileges_and_database_users(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $database = $this->makeDatabase();
        $databaseUser = DatabaseUser::query()->create([
            'server_id' => $this->server->id,
            'username' => 'someuser',
            'password' => 'secret',
        ]);

        $this->assertTrue($admin->can('managePrivileges', $database));
        $this->assertTrue($admin->can('create', DatabaseUser::class));
        $this->assertTrue($admin->can('delete', $databaseUser));
    }

    public function test_developer_cannot_create_or_delete_database_users(): void
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        $databaseUser = DatabaseUser::query()->create([
            'server_id' => $this->server->id,
            'username' => 'someuser',
            'password' => 'secret',
        ]);

        $this->assertFalse($developer->can('create', DatabaseUser::class));
        $this->assertFalse($developer->can('delete', $databaseUser));
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeDatabase(array $overrides = []): Database
    {
        return Database::query()->create(array_merge([
            'server_id' => $this->server->id,
            'name' => 'db_'.substr(md5(uniqid('', true)), 0, 10),
        ], $overrides));
    }
}
