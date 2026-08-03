<?php

declare(strict_types=1);

namespace App\Actions\Cloudflare;

use App\DTOs\Cloudflare\CloudflareApiResult;
use App\Models\CloudflareZone;
use App\Services\Cloudflare\CloudflareZoneService;

class PurgeCacheAction
{
    public function __construct(
        private readonly CloudflareZoneService $zones,
    ) {}

    public function handle(CloudflareZone $zone): CloudflareApiResult
    {
        $result = $this->zones->purgeCache($zone);

        activity('cloudflare')
            ->causedBy(auth()->user())
            ->performedOn($zone->website)
            ->withProperties(['successful' => $result->successful])
            ->log('purged cache');

        return $result;
    }
}
