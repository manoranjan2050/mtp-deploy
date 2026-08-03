<?php

declare(strict_types=1);

namespace App\Actions\Cloudflare;

use App\DTOs\Cloudflare\CloudflareApiResult;
use App\Models\CloudflareTunnel;
use App\Services\Cloudflare\CloudflareTunnelService;

class DestroyTunnelAction
{
    public function __construct(
        private readonly CloudflareTunnelService $tunnels,
    ) {}

    public function handle(CloudflareTunnel $tunnel): CloudflareApiResult
    {
        $server = $tunnel->server;
        $name = $tunnel->name;

        $result = $this->tunnels->destroyTunnel($tunnel);

        activity('cloudflare')
            ->causedBy(auth()->user())
            ->performedOn($server)
            ->withProperties(['name' => $name, 'successful' => $result->successful])
            ->log('destroyed cloudflare tunnel');

        return $result;
    }
}
