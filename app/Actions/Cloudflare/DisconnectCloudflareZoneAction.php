<?php

declare(strict_types=1);

namespace App\Actions\Cloudflare;

use App\Models\CloudflareZone;
use App\Services\Cloudflare\CloudflareZoneService;

class DisconnectCloudflareZoneAction
{
    public function __construct(
        private readonly CloudflareZoneService $zones,
    ) {}

    public function handle(CloudflareZone $zone): void
    {
        $website = $zone->website;

        $this->zones->disconnect($zone);

        activity('cloudflare')
            ->causedBy(auth()->user())
            ->performedOn($website)
            ->log('disconnected cloudflare zone');
    }
}
