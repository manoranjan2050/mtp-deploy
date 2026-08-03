<?php

declare(strict_types=1);

namespace App\Actions\Cloudflare;

use App\DTOs\Cloudflare\CloudflareApiResult;
use App\Models\CloudflareZone;
use App\Services\Cloudflare\CloudflareZoneService;

class DeleteDnsRecordAction
{
    public function __construct(
        private readonly CloudflareZoneService $zones,
    ) {}

    public function handle(CloudflareZone $zone, string $recordId): CloudflareApiResult
    {
        $result = $this->zones->deleteDnsRecord($zone, $recordId);

        activity('cloudflare')
            ->causedBy(auth()->user())
            ->performedOn($zone->website)
            ->withProperties(['record_id' => $recordId, 'successful' => $result->successful])
            ->log('deleted DNS record');

        return $result;
    }
}
