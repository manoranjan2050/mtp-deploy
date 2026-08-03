<?php

declare(strict_types=1);

namespace App\Actions\Cloudflare;

use App\DTOs\Cloudflare\CloudflareApiResult;
use App\Enums\CloudflareSslMode;
use App\Models\CloudflareZone;
use App\Services\Cloudflare\CloudflareZoneService;

class UpdateSslModeAction
{
    public function __construct(
        private readonly CloudflareZoneService $zones,
    ) {}

    public function handle(CloudflareZone $zone, CloudflareSslMode $mode): CloudflareApiResult
    {
        $result = $this->zones->updateSslMode($zone, $mode);

        activity('cloudflare')
            ->causedBy(auth()->user())
            ->performedOn($zone->website)
            ->withProperties(['ssl_mode' => $mode->value, 'successful' => $result->successful])
            ->log('updated SSL mode');

        return $result;
    }
}
