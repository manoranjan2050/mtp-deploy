<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Cloudflare;

use App\Enums\CloudflareSslMode;
use App\Services\Cloudflare\CloudflareApiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unlike every other Module 4-8 service test in this project, this one uses
 * `Http::fake()` rather than a real external call - there is no real
 * Cloudflare account/zone/API token available in this dev environment, and
 * unlike MySQL/git/composer, Cloudflare is a third-party SaaS this project
 * cannot stand up locally. The fakes below assert against Cloudflare's real,
 * documented API v4 request/response shapes (envelope: success/errors/result)
 * so they'd catch a genuine shape mismatch, even though they can't catch a
 * live-account integration problem. See CLAUDE.md for the full reasoning and
 * the standing recommendation to do one manual smoke test against a real
 * zone before relying on this in production.
 */
class CloudflareApiClientTest extends TestCase
{
    public function test_it_lists_dns_records(): void
    {
        Http::fake([
            'api.cloudflare.com/*' => Http::response([
                'success' => true,
                'errors' => [],
                'result' => [
                    ['id' => 'rec1', 'type' => 'A', 'name' => 'example.com', 'content' => '1.2.3.4', 'ttl' => 1, 'proxied' => true],
                ],
            ]),
        ]);

        $result = app(CloudflareApiClient::class)->listDnsRecords('zone123', 'token123');

        $this->assertTrue($result->successful);
        $this->assertCount(1, $result->data);
        $this->assertSame('example.com', $result->data[0]->name);
        $this->assertTrue($result->data[0]->proxied);

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer token123')
            && str_contains((string) $request->url(), '/zones/zone123/dns_records'));
    }

    public function test_it_creates_a_dns_record(): void
    {
        Http::fake([
            'api.cloudflare.com/*' => Http::response([
                'success' => true,
                'errors' => [],
                'result' => ['id' => 'rec2', 'type' => 'A', 'name' => 'app.example.com', 'content' => '5.6.7.8', 'ttl' => 300, 'proxied' => false],
            ]),
        ]);

        $result = app(CloudflareApiClient::class)->createDnsRecord('zone123', 'token123', 'A', 'app.example.com', '5.6.7.8', 300, false);

        $this->assertTrue($result->successful);
        $this->assertSame('rec2', $result->data->id);
    }

    public function test_it_surfaces_a_failed_request(): void
    {
        Http::fake([
            'api.cloudflare.com/*' => Http::response([
                'success' => false,
                'errors' => [['code' => 1003, 'message' => 'Invalid zone identifier']],
                'result' => null,
            ], 400),
        ]);

        $result = app(CloudflareApiClient::class)->listDnsRecords('bad-zone', 'token123');

        $this->assertFalse($result->successful);
        $this->assertSame(['Invalid zone identifier'], $result->errors);
    }

    public function test_it_updates_ssl_mode(): void
    {
        Http::fake([
            'api.cloudflare.com/*' => Http::response(['success' => true, 'errors' => [], 'result' => ['id' => 'ssl', 'value' => 'full']]),
        ]);

        $result = app(CloudflareApiClient::class)->updateSslMode('zone123', 'token123', CloudflareSslMode::Full);

        $this->assertTrue($result->successful);

        Http::assertSent(fn ($request): bool => $request->method() === 'PATCH'
            && $request['value'] === 'full');
    }

    public function test_it_purges_cache(): void
    {
        Http::fake([
            'api.cloudflare.com/*' => Http::response(['success' => true, 'errors' => [], 'result' => ['id' => 'zone123']]),
        ]);

        $result = app(CloudflareApiClient::class)->purgeCache('zone123', 'token123');

        $this->assertTrue($result->successful);

        Http::assertSent(fn ($request): bool => $request['purge_everything'] === true);
    }

    public function test_it_deletes_a_dns_record(): void
    {
        Http::fake([
            'api.cloudflare.com/*' => Http::response(['success' => true, 'errors' => [], 'result' => ['id' => 'rec1']]),
        ]);

        $result = app(CloudflareApiClient::class)->deleteDnsRecord('zone123', 'token123', 'rec1');

        $this->assertTrue($result->successful);

        Http::assertSent(fn ($request): bool => $request->method() === 'DELETE');
    }
}
