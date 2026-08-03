<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Docker;

use App\Services\Docker\DockerApiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unlike Modules 4-8's real local infrastructure, this dev environment has
 * no Docker daemon reachable at all (`docker` isn't even installed).
 * `Http::fake()` verifies Docker Engine's real, documented request/response
 * shapes (https://docs.docker.com/engine/api/) instead of a live daemon
 * round-trip, the same disclosed deviation as Module 9's Cloudflare and
 * Module 16's Telegram/Discord/Slack - see CLAUDE.md.
 */
class DockerApiClientTest extends TestCase
{
    public function test_it_honestly_reports_failure_when_the_daemon_is_genuinely_unreachable(): void
    {
        // A real network-level failure (nothing listening on this port), not
        // a faked HTTP response - proves ConnectionException is caught and
        // turned into an honest failure result instead of an uncaught 500.
        config(['services.docker.base_url' => 'http://127.0.0.1:1']);

        $result = app(DockerApiClient::class)->listContainers();

        $this->assertFalse($result->successful);
        $this->assertNotEmpty($result->errors);
    }

    public function test_it_lists_containers(): void
    {
        Http::fake([
            '*/containers/json*' => Http::response([
                [
                    'Id' => 'abc123',
                    'Names' => ['/my-app'],
                    'Image' => 'nginx:latest',
                    'State' => 'running',
                    'Status' => 'Up 2 hours',
                ],
            ]),
        ]);

        $result = app(DockerApiClient::class)->listContainers();

        $this->assertTrue($result->successful);
        $this->assertCount(1, $result->data);
        $this->assertSame('my-app', $result->data[0]->name);
        $this->assertSame('running', $result->data[0]->state);

        Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/containers/json'));
    }

    public function test_it_reports_failure_when_the_daemon_is_unreachable(): void
    {
        Http::fake([
            '*/containers/json*' => Http::response(['message' => 'connection refused'], 500),
        ]);

        $result = app(DockerApiClient::class)->listContainers();

        $this->assertFalse($result->successful);
        $this->assertSame(['connection refused'], $result->errors);
    }

    public function test_it_starts_a_container(): void
    {
        Http::fake(['*/containers/abc123/start' => Http::response('', 204)]);

        $result = app(DockerApiClient::class)->startContainer('abc123');

        $this->assertTrue($result->successful);
        Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/containers/abc123/start'));
    }

    public function test_it_stops_a_container(): void
    {
        Http::fake(['*/containers/abc123/stop' => Http::response('', 204)]);

        $result = app(DockerApiClient::class)->stopContainer('abc123');

        $this->assertTrue($result->successful);
    }

    public function test_it_restarts_a_container(): void
    {
        Http::fake(['*/containers/abc123/restart' => Http::response('', 204)]);

        $result = app(DockerApiClient::class)->restartContainer('abc123');

        $this->assertTrue($result->successful);
    }

    public function test_it_lists_images(): void
    {
        Http::fake([
            '*/images/json' => Http::response([
                ['Id' => 'sha256:xyz', 'RepoTags' => ['nginx:latest'], 'Size' => 142000000],
            ]),
        ]);

        $result = app(DockerApiClient::class)->listImages();

        $this->assertTrue($result->successful);
        $this->assertSame('nginx:latest', $result->data[0]->tag);
        $this->assertSame(142000000, $result->data[0]->sizeBytes);
    }

    public function test_it_pulls_an_image(): void
    {
        Http::fake(['*/images/create*' => Http::response('', 200)]);

        $result = app(DockerApiClient::class)->pullImage('nginx:latest');

        $this->assertTrue($result->successful);
        Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), 'fromImage=nginx%3Alatest'));
    }

    public function test_it_removes_an_image(): void
    {
        Http::fake(['*/images/sha256:xyz' => Http::response('', 204)]);

        $result = app(DockerApiClient::class)->removeImage('sha256:xyz');

        $this->assertTrue($result->successful);
    }
}
