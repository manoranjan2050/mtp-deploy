<?php

declare(strict_types=1);

namespace Tests\Feature\Cloudflare;

use App\Actions\Cloudflare\ConnectCloudflareZoneAction;
use App\Actions\Cloudflare\CreateDnsRecordAction;
use App\Actions\Cloudflare\CreateTunnelAction;
use App\Actions\Cloudflare\DestroyTunnelAction;
use App\Actions\Cloudflare\DisconnectCloudflareZoneAction;
use App\Actions\Cloudflare\PurgeCacheAction;
use App\Actions\Cloudflare\UpdateSslModeAction;
use App\Enums\CloudflareSslMode;
use App\Enums\WebsiteFramework;
use App\Models\CloudflareTunnel;
use App\Models\Server;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CloudflareActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        Http::fake([
            'api.cloudflare.com/*' => Http::response([
                'success' => true,
                'errors' => [],
                'result' => ['id' => 'rec1', 'type' => 'A', 'name' => 'example.com', 'content' => '1.2.3.4', 'ttl' => 1, 'proxied' => false],
            ]),
        ]);
    }

    public function test_connect_action_creates_a_zone_and_logs_activity(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $website = $this->website();

        $zone = app(ConnectCloudflareZoneAction::class)->handle($website, 'zone123', 'token123');

        $this->assertSame('zone123', $zone->zone_id);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'cloudflare', 'description' => 'connected cloudflare zone']);
    }

    public function test_disconnect_action_removes_the_zone(): void
    {
        $this->actingAs($this->admin());
        $website = $this->website();

        $zone = app(ConnectCloudflareZoneAction::class)->handle($website, 'zone123', 'token123');
        app(DisconnectCloudflareZoneAction::class)->handle($zone);

        $this->assertDatabaseMissing('cloudflare_zones', ['id' => $zone->id]);
    }

    public function test_create_dns_record_and_purge_cache_and_ssl_mode_actions_work(): void
    {
        $this->actingAs($this->admin());
        $website = $this->website();
        $zone = app(ConnectCloudflareZoneAction::class)->handle($website, 'zone123', 'token123');

        $dnsResult = app(CreateDnsRecordAction::class)->handle($zone, 'A', 'example.com', '1.2.3.4');
        $this->assertTrue($dnsResult->successful);

        $purgeResult = app(PurgeCacheAction::class)->handle($zone);
        $this->assertTrue($purgeResult->successful);

        $sslResult = app(UpdateSslModeAction::class)->handle($zone, CloudflareSslMode::Full);
        $this->assertTrue($sslResult->successful);
        $this->assertSame(CloudflareSslMode::Full, $zone->fresh()->ssl_mode);
    }

    public function test_developer_can_connect_a_zone_on_their_own_website_but_not_anothers(): void
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        $ownWebsite = $this->website(['created_by' => $developer->id, 'domain' => 'own.example.com']);
        $othersWebsite = $this->website(['domain' => 'others.example.com']);

        $this->assertTrue($developer->can('update', $ownWebsite));
        $this->assertFalse($developer->can('update', $othersWebsite));
    }

    public function test_only_admin_can_manage_cloudflare_tunnels(): void
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);

        $this->assertFalse($developer->can('manageTunnels', $server));
        $this->assertTrue($this->admin()->can('manageTunnels', $server));
    }

    public function test_create_and_destroy_tunnel_actions(): void
    {
        $this->actingAs($this->admin());

        Http::fake([
            'api.cloudflare.com/*' => Http::response(['success' => true, 'errors' => [], 'result' => ['id' => 'tunnel-abc']]),
        ]);

        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);

        $result = app(CreateTunnelAction::class)->handle($server, 'prod-tunnel');
        $this->assertTrue($result->successful);

        $tunnel = CloudflareTunnel::query()->firstOrFail();

        $destroyResult = app(DestroyTunnelAction::class)->handle($tunnel);
        $this->assertTrue($destroyResult->successful);
        $this->assertDatabaseMissing('cloudflare_tunnels', ['id' => $tunnel->id]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function website(array $overrides = []): Website
    {
        $server = Server::query()->create(['name' => 'Test Server '.uniqid(), 'is_local' => false]);

        return Website::query()->create(array_merge([
            'server_id' => $server->id,
            'name' => 'Example',
            'domain' => 'example.com',
            'document_root' => '/var/www/example.com',
            'php_version' => '8.3',
            'framework' => WebsiteFramework::Laravel,
        ], $overrides));
    }
}
