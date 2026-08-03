<?php

declare(strict_types=1);

namespace App\Actions\Cloudflare;

use App\DTOs\Cloudflare\CloudflareApiResult;
use App\Models\Server;
use App\Services\Cloudflare\CloudflareTunnelService;

class CreateTunnelAction
{
    public function __construct(
        private readonly CloudflareTunnelService $tunnels,
    ) {}

    public function handle(Server $server, string $name): CloudflareApiResult
    {
        $result = $this->tunnels->createTunnel($server, $name);

        activity('cloudflare')
            ->causedBy(auth()->user())
            ->performedOn($server)
            ->withProperties(['name' => $name, 'successful' => $result->successful])
            ->log('created cloudflare tunnel');

        return $result;
    }
}
