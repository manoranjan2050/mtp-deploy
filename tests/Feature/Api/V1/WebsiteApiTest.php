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
use Tests\TestCase;

class WebsiteApiTest extends TestCase
{
    use RefreshDatabase;

    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $this->tempRoot = sys_get_temp_dir().'/mtp-website-api-test-'.uniqid();

        config([
            'mtp.nginx_sites_available_path' => $this->tempRoot.'/sites-available',
            'mtp.nginx_sites_enabled_path' => $this->tempRoot.'/sites-enabled',
            'mtp.sites_root' => $this->tempRoot.'/www',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempRoot);

        parent::tearDown();
    }

    public function test_a_token_without_websites_read_cannot_list_websites(): void
    {
        $user = $this->admin();
        Sanctum::actingAs($user, ['profile:read']);

        $this->getJson('/api/v1/websites')->assertForbidden();
    }

    public function test_it_lists_websites_for_a_token_with_websites_read(): void
    {
        $user = $this->admin();
        $this->website();
        Sanctum::actingAs($user, ['websites:read']);

        $response = $this->getJson('/api/v1/websites');

        $response->assertSuccessful();
        $response->assertJsonCount(1, 'data');
    }

    public function test_it_shows_a_single_website(): void
    {
        $user = $this->admin();
        $website = $this->website();
        Sanctum::actingAs($user, ['websites:read']);

        $this->getJson("/api/v1/websites/{$website->id}")
            ->assertSuccessful()
            ->assertJsonPath('data.domain', $website->domain);
    }

    public function test_it_creates_a_website_with_websites_write(): void
    {
        $user = $this->admin();
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);
        Sanctum::actingAs($user, ['websites:write']);

        $response = $this->postJson('/api/v1/websites', [
            'server_id' => $server->id,
            'name' => 'Example',
            'domain' => 'api-created.test',
            'php_version' => '8.3',
            'framework' => 'laravel',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('websites', ['domain' => 'api-created.test']);
    }

    public function test_it_cannot_create_a_website_without_websites_write(): void
    {
        $user = $this->admin();
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);
        Sanctum::actingAs($user, ['websites:read']);

        $this->postJson('/api/v1/websites', [
            'server_id' => $server->id,
            'name' => 'Example',
            'domain' => 'blocked.test',
            'php_version' => '8.3',
            'framework' => 'laravel',
        ])->assertForbidden();
    }

    public function test_it_updates_a_website(): void
    {
        $user = $this->admin();
        $website = $this->website();
        Sanctum::actingAs($user, ['websites:write']);

        $this->patchJson("/api/v1/websites/{$website->id}", ['name' => 'Renamed'])
            ->assertSuccessful()
            ->assertJsonPath('data.name', 'Renamed');
    }

    public function test_it_suspends_a_website(): void
    {
        $user = $this->admin();
        $website = $this->website();
        Sanctum::actingAs($user, ['websites:write']);

        $this->postJson("/api/v1/websites/{$website->id}/suspend")
            ->assertSuccessful();

        $this->assertSame('suspended', $website->fresh()->status->value);
    }

    public function test_it_deletes_a_website(): void
    {
        $user = $this->admin();
        $website = $this->website();
        Sanctum::actingAs($user, ['websites:write']);

        $this->deleteJson("/api/v1/websites/{$website->id}")->assertNoContent();

        $this->assertSoftDeleted('websites', ['id' => $website->id]);
    }

    public function test_a_developer_token_only_sees_websites_they_created(): void
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');
        $other = $this->admin();

        $mine = $this->website(['created_by' => $developer->id]);
        $this->website(['created_by' => $other->id, 'domain' => 'not-mine.test']);

        Sanctum::actingAs($developer, ['websites:read']);

        $response = $this->getJson('/api/v1/websites');

        $response->assertSuccessful();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $mine->id);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function website(array $overrides = []): Website
    {
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);

        return Website::query()->create(array_merge([
            'server_id' => $server->id,
            'name' => 'Example',
            'domain' => 'api-test-'.uniqid().'.test',
            'document_root' => sys_get_temp_dir().'/mtp-api-test-'.uniqid(),
            'php_version' => '8.3',
            'framework' => WebsiteFramework::Laravel,
        ], $overrides));
    }
}
