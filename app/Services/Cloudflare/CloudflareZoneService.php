<?php

declare(strict_types=1);

namespace App\Services\Cloudflare;

use App\DTOs\Cloudflare\CloudflareApiResult;
use App\Enums\CloudflareSslMode;
use App\Models\CloudflareZone;
use App\Models\Website;

class CloudflareZoneService
{
    public function __construct(
        private readonly CloudflareApiClient $client,
    ) {}

    public function connect(Website $website, string $zoneId, string $apiToken): CloudflareZone
    {
        return CloudflareZone::query()->updateOrCreate(
            ['website_id' => $website->id],
            ['zone_id' => $zoneId, 'api_token' => $apiToken, 'last_synced_at' => now()],
        );
    }

    public function disconnect(CloudflareZone $zone): void
    {
        $zone->delete();
    }

    public function listDnsRecords(CloudflareZone $zone): CloudflareApiResult
    {
        return $this->client->listDnsRecords($zone->zone_id, $zone->api_token);
    }

    public function createDnsRecord(CloudflareZone $zone, string $type, string $name, string $content, int $ttl = 1, bool $proxied = false): CloudflareApiResult
    {
        return $this->client->createDnsRecord($zone->zone_id, $zone->api_token, $type, $name, $content, $ttl, $proxied);
    }

    public function deleteDnsRecord(CloudflareZone $zone, string $recordId): CloudflareApiResult
    {
        return $this->client->deleteDnsRecord($zone->zone_id, $zone->api_token, $recordId);
    }

    public function updateSslMode(CloudflareZone $zone, CloudflareSslMode $mode): CloudflareApiResult
    {
        $result = $this->client->updateSslMode($zone->zone_id, $zone->api_token, $mode);

        if ($result->successful) {
            $zone->update(['ssl_mode' => $mode, 'last_synced_at' => now()]);
        }

        return $result;
    }

    public function purgeCache(CloudflareZone $zone, ?array $files = null): CloudflareApiResult
    {
        return $this->client->purgeCache($zone->zone_id, $zone->api_token, $files);
    }
}
