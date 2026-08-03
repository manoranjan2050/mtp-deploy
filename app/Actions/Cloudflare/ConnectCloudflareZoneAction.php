<?php

declare(strict_types=1);

namespace App\Actions\Cloudflare;

use App\Models\CloudflareZone;
use App\Models\Website;
use App\Services\Cloudflare\CloudflareZoneService;

class ConnectCloudflareZoneAction
{
    public function __construct(
        private readonly CloudflareZoneService $zones,
    ) {}

    public function handle(Website $website, string $zoneId, string $apiToken): CloudflareZone
    {
        $zone = $this->zones->connect($website, $zoneId, $apiToken);

        activity('cloudflare')
            ->causedBy(auth()->user())
            ->performedOn($website)
            ->withProperties(['zone_id' => $zoneId])
            ->log('connected cloudflare zone');

        return $zone;
    }
}
