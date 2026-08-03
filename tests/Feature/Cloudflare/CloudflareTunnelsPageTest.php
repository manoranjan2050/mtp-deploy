<?php

declare(strict_types=1);

namespace Tests\Feature\Cloudflare;

use App\Filament\Pages\CloudflareTunnels;
use App\Models\CloudflareTunnel;
use App\Models\Server;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CloudflareTunnelsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        Server::query()->create(['name' => 'Test Server', 'is_local' => true]);
    }

    public function test_an_admin_can_create_and_destroy_a_tunnel(): void
    {
        Http::fake([
            'api.cloudflare.com/*' => Http::response(['success' => true, 'errors' => [], 'result' => ['id' => 'tunnel-abc']]),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $component = Livewire::actingAs($admin)
            ->test(CloudflareTunnels::class)
            ->assertSuccessful()
            ->set('newTunnelName', 'prod-tunnel')
            ->call('createTunnel')
            ->assertSee('prod-tunnel');

        $this->assertDatabaseHas('cloudflare_tunnels', ['name' => 'prod-tunnel']);

        $tunnelId = CloudflareTunnel::query()->firstOrFail()->id;

        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true, 'errors' => [], 'result' => null])]);

        $component->call('destroyTunnel', $tunnelId);

        $this->assertDatabaseMissing('cloudflare_tunnels', ['id' => $tunnelId]);
    }

    public function test_a_developer_cannot_access_the_tunnels_page(): void
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        Livewire::actingAs($developer)
            ->test(CloudflareTunnels::class)
            ->assertForbidden();
    }
}
