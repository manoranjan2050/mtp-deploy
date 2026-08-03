<?php

declare(strict_types=1);

namespace App\Actions\Cloudflare;

use App\DTOs\Cloudflare\CloudflareApiResult;
use App\Models\CloudflareZone;
use App\Services\Cloudflare\CloudflareZoneService;

class CreateDnsRecordAction
{
    public function __construct(
        private readonly CloudflareZoneService $zones,
    ) {}

    public function handle(CloudflareZone $zone, string $type, string $name, string $content, int $ttl = 1, bool $proxied = false): CloudflareApiResult
    {
        $result = $this->zones->createDnsRecord($zone, $type, $name, $content, $ttl, $proxied);

        activity('cloudflare')
            ->causedBy(auth()->user())
            ->performedOn($zone->website)
            ->withProperties(['type' => $type, 'name' => $name, 'content' => $content, 'successful' => $result->successful])
            ->log('created DNS record');

        return $result;
    }
}
