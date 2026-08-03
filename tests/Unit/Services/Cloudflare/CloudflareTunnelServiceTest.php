<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Cloudflare;

use App\Models\CloudflareTunnel;
use App\Models\Server;
use App\Services\Cloudflare\CloudflareTunnelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CloudflareTunnelServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.cloudflare.account_id' => 'account123',
            'services.cloudflare.account_api_token' => 'account-token',
        ]);
    }

    public function test_it_creates_a_tunnel_and_persists_it_locally(): void
    {
        Http::fake([
            'api.cloudflare.com/*' => Http::response([
                'success' => true,
                'errors' => [],
                'result' => ['id' => 'tunnel-abc', 'name' => 'prod-tunnel'],
            ]),
        ]);

        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);

        $result = app(CloudflareTunnelService::class)->createTunnel($server, 'prod-tunnel');

        $this->assertTrue($result->successful);
        $this->assertDatabaseHas('cloudflare_tunnels', [
            'server_id' => $server->id,
            'cloudflare_tunnel_id' => 'tunnel-abc',
            'name' => 'prod-tunnel',
        ]);
    }

    public function test_a_failed_creation_does_not_persist_a_local_row(): void
    {
        Http::fake([
            'api.cloudflare.com/*' => Http::response(['success' => false, 'errors' => [['message' => 'bad token']]], 400),
        ]);

        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);

        $result = app(CloudflareTunnelService::class)->createTunnel($server, 'prod-tunnel');

        $this->assertFalse($result->successful);
        $this->assertDatabaseCount('cloudflare_tunnels', 0);
    }

    public function test_it_destroys_a_tunnel_and_removes_the_local_row(): void
    {
        Http::fake([
            'api.cloudflare.com/*' => Http::response(['success' => true, 'errors' => [], 'result' => null]),
        ]);

        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);
        $tunnel = CloudflareTunnel::query()->create([
            'server_id' => $server->id,
            'cloudflare_tunnel_id' => 'tunnel-abc',
            'name' => 'prod-tunnel',
        ]);

        $result = app(CloudflareTunnelService::class)->destroyTunnel($tunnel);

        $this->assertTrue($result->successful);
        $this->assertDatabaseMissing('cloudflare_tunnels', ['id' => $tunnel->id]);
    }
}
