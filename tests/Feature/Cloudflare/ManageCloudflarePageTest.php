<?php

declare(strict_types=1);

namespace Tests\Feature\Cloudflare;

use App\Enums\WebsiteFramework;
use App\Filament\Resources\Websites\Pages\ManageCloudflare;
use App\Models\Server;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Renders the real ManageCloudflare Livewire page end-to-end - same
 * "render the real page" pattern as ManageFilesPageTest (Module 7) and
 * TerminalPageTest (Module 8). Cloudflare's own API is faked (see
 * CloudflareApiClientTest's docblock for why), but the page/Action/policy
 * wiring around it is exercised for real.
 */
class ManageCloudflarePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    public function test_it_shows_the_connect_form_when_no_zone_exists(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManageCloudflare::class, ['record' => $this->website()->getKey()])
            ->assertSuccessful()
            ->assertSee('Connect Cloudflare');
    }

    public function test_it_connects_a_zone_and_then_shows_zone_management(): void
    {
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true, 'errors' => [], 'result' => []])]);

        $website = $this->website();

        Livewire::actingAs($this->admin())
            ->test(ManageCloudflare::class, ['record' => $website->getKey()])
            ->set('zoneId', 'zone123')
            ->set('apiToken', 'token123')
            ->call('connect')
            ->assertSee('Zone ID');

        $this->assertDatabaseHas('cloudflare_zones', ['website_id' => $website->id, 'zone_id' => 'zone123']);
    }

    public function test_a_viewer_cannot_access_the_page(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        Livewire::actingAs($viewer)
            ->test(ManageCloudflare::class, ['record' => $this->website()->getKey()])
            ->assertForbidden();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
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
