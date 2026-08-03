<?php

declare(strict_types=1);

namespace App\Services\Cloudflare;

use App\DTOs\Cloudflare\CloudflareApiResult;
use App\DTOs\Cloudflare\DnsRecordData;
use App\Enums\CloudflareSslMode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * A thin wrapper over Cloudflare's real REST API v4
 * (https://developers.cloudflare.com/api/). This dev environment has no real
 * Cloudflare account/zone to test against - unlike Modules 4-8, which all
 * exercised genuine local infrastructure (MySQL, git, composer, the
 * filesystem), Cloudflare is a third-party SaaS that needs real account
 * credentials this environment doesn't have. Tests use `Http::fake()`
 * against Cloudflare's real, documented request/response shapes instead - see
 * CLAUDE.md for the full explanation of this deliberate, honest deviation.
 * A real zone/token pair should be used for one manual smoke test before this
 * module is considered production-ready.
 */
class CloudflareApiClient
{
    public function listDnsRecords(string $zoneId, string $apiToken): CloudflareApiResult
    {
        $response = $this->request($apiToken)->get("/zones/{$zoneId}/dns_records");

        if (! $this->wasSuccessful($response)) {
            return $this->failure($response);
        }

        $records = collect($response->json('result', []))
            ->map(fn (array $record): DnsRecordData => DnsRecordData::fromApiResponse($record))
            ->values()
            ->all();

        return new CloudflareApiResult(successful: true, data: $records);
    }

    public function createDnsRecord(string $zoneId, string $apiToken, string $type, string $name, string $content, int $ttl = 1, bool $proxied = false): CloudflareApiResult
    {
        $response = $this->request($apiToken)->post("/zones/{$zoneId}/dns_records", [
            'type' => $type,
            'name' => $name,
            'content' => $content,
            'ttl' => $ttl,
            'proxied' => $proxied,
        ]);

        if (! $this->wasSuccessful($response)) {
            return $this->failure($response);
        }

        return new CloudflareApiResult(successful: true, data: DnsRecordData::fromApiResponse($response->json('result')));
    }

    public function deleteDnsRecord(string $zoneId, string $apiToken, string $recordId): CloudflareApiResult
    {
        $response = $this->request($apiToken)->delete("/zones/{$zoneId}/dns_records/{$recordId}");

        if (! $this->wasSuccessful($response)) {
            return $this->failure($response);
        }

        return new CloudflareApiResult(successful: true);
    }

    public function updateSslMode(string $zoneId, string $apiToken, CloudflareSslMode $mode): CloudflareApiResult
    {
        $response = $this->request($apiToken)->patch("/zones/{$zoneId}/settings/ssl", [
            'value' => $mode->value,
        ]);

        if (! $this->wasSuccessful($response)) {
            return $this->failure($response);
        }

        return new CloudflareApiResult(successful: true);
    }

    public function purgeCache(string $zoneId, string $apiToken, ?array $files = null): CloudflareApiResult
    {
        $payload = $files === null ? ['purge_everything' => true] : ['files' => $files];

        $response = $this->request($apiToken)->post("/zones/{$zoneId}/purge_cache", $payload);

        if (! $this->wasSuccessful($response)) {
            return $this->failure($response);
        }

        return new CloudflareApiResult(successful: true);
    }

    public function listTunnels(string $accountId, string $apiToken): CloudflareApiResult
    {
        $response = $this->request($apiToken)->get("/accounts/{$accountId}/cfd_tunnel");

        if (! $this->wasSuccessful($response)) {
            return $this->failure($response);
        }

        return new CloudflareApiResult(successful: true, data: $response->json('result', []));
    }

    public function createTunnel(string $accountId, string $apiToken, string $name, string $tunnelSecret): CloudflareApiResult
    {
        $response = $this->request($apiToken)->post("/accounts/{$accountId}/cfd_tunnel", [
            'name' => $name,
            'tunnel_secret' => $tunnelSecret,
        ]);

        if (! $this->wasSuccessful($response)) {
            return $this->failure($response);
        }

        return new CloudflareApiResult(successful: true, data: $response->json('result'));
    }

    public function deleteTunnel(string $accountId, string $apiToken, string $tunnelId): CloudflareApiResult
    {
        $response = $this->request($apiToken)->delete("/accounts/{$accountId}/cfd_tunnel/{$tunnelId}");

        if (! $this->wasSuccessful($response)) {
            return $this->failure($response);
        }

        return new CloudflareApiResult(successful: true);
    }

    private function request(string $apiToken): PendingRequest
    {
        return Http::withToken($apiToken)
            ->baseUrl((string) config('services.cloudflare.base_url'))
            ->acceptJson()
            ->timeout(15);
    }

    private function wasSuccessful(Response $response): bool
    {
        return $response->successful() && $response->json('success') === true;
    }

    /**
     * @return array<int, string>
     */
    private function extractErrors(Response $response): array
    {
        $errors = collect($response->json('errors', []))
            ->map(fn (array $error): string => $error['message'] ?? 'Unknown Cloudflare API error')
            ->all();

        return $errors === [] ? ['Cloudflare API request failed (HTTP '.$response->status().')'] : $errors;
    }

    private function failure(Response $response): CloudflareApiResult
    {
        $errors = $this->extractErrors($response);

        Log::warning('Cloudflare API request failed', ['errors' => $errors, 'status' => $response->status()]);

        return new CloudflareApiResult(successful: false, errors: $errors);
    }
}
