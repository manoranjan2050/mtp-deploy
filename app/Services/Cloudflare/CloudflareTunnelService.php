<?php

declare(strict_types=1);

namespace App\Services\Cloudflare;

use App\DTOs\Cloudflare\CloudflareApiResult;
use App\Models\CloudflareTunnel;
use App\Models\Server;
use Illuminate\Support\Str;

/**
 * Tunnels are Cloudflare-account-scoped (one account_id/token pair, see
 * config/services.php), unlike DNS/SSL/cache which are per-website zones.
 * This service only orchestrates the tunnel *object* via Cloudflare's API and
 * this panel's own `cloudflare_tunnels` record of what it created - it does
 * not install or run the real `cloudflared` connector daemon on the server,
 * which is a genuine, deliberate scope gap for this module (see CLAUDE.md):
 * a tunnel created here has no traffic flowing through it until a real
 * `cloudflared tunnel run` process is started on that server, exactly like a
 * real Cloudflare tunnel with no connector attached.
 */
class CloudflareTunnelService
{
    public function __construct(
        private readonly CloudflareApiClient $client,
    ) {}

    public function createTunnel(Server $server, string $name): CloudflareApiResult
    {
        $accountId = (string) config('services.cloudflare.account_id');
        $accountToken = (string) config('services.cloudflare.account_api_token');

        $result = $this->client->createTunnel($accountId, $accountToken, $name, base64_encode(Str::random(32)));

        if ($result->successful) {
            CloudflareTunnel::query()->create([
                'server_id' => $server->id,
                'cloudflare_tunnel_id' => $result->data['id'],
                'name' => $name,
            ]);
        }

        return $result;
    }

    public function destroyTunnel(CloudflareTunnel $tunnel): CloudflareApiResult
    {
        $accountId = (string) config('services.cloudflare.account_id');
        $accountToken = (string) config('services.cloudflare.account_api_token');

        $result = $this->client->deleteTunnel($accountId, $accountToken, $tunnel->cloudflare_tunnel_id);

        if ($result->successful) {
            $tunnel->delete();
        }

        return $result;
    }
}
