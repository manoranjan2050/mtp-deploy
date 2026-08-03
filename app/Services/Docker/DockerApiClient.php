<?php

declare(strict_types=1);

namespace App\Services\Docker;

use App\DTOs\Docker\DockerApiResult;
use App\DTOs\Docker\DockerContainerData;
use App\DTOs\Docker\DockerImageData;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * A thin wrapper over Docker Engine's real REST API
 * (https://docs.docker.com/engine/api/) - not a placeholder. This dev
 * environment has no Docker daemon reachable at all (`docker` isn't even
 * installed), unlike Modules 4-8 which all exercised genuine local
 * infrastructure. Tests use `Http::fake()` against Docker Engine's real,
 * documented request/response shapes instead - the same disclosed deviation
 * as Module 9's Cloudflare and Module 16's Telegram/Discord/Slack (see
 * CLAUDE.md). A real reachable Docker Engine API endpoint should be used for
 * one manual smoke test before this module is considered production-ready.
 */
class DockerApiClient
{
    /**
     * @return DockerApiResult with data: list<DockerContainerData>
     */
    public function listContainers(): DockerApiResult
    {
        $response = $this->attempt(fn (): Response => $this->request()->get('/containers/json', ['all' => 'true']));

        if ($response === null || ! $response->successful()) {
            return $this->failure($response);
        }

        $containers = collect($response->json())
            ->map(fn (array $container): DockerContainerData => DockerContainerData::fromApiResponse($container))
            ->values()
            ->all();

        return new DockerApiResult(successful: true, data: $containers);
    }

    public function startContainer(string $id): DockerApiResult
    {
        return $this->resultForNoContentResponse(
            $this->attempt(fn (): Response => $this->request()->post("/containers/{$id}/start"))
        );
    }

    public function stopContainer(string $id): DockerApiResult
    {
        return $this->resultForNoContentResponse(
            $this->attempt(fn (): Response => $this->request()->post("/containers/{$id}/stop"))
        );
    }

    public function restartContainer(string $id): DockerApiResult
    {
        return $this->resultForNoContentResponse(
            $this->attempt(fn (): Response => $this->request()->post("/containers/{$id}/restart"))
        );
    }

    /**
     * @return DockerApiResult with data: list<DockerImageData>
     */
    public function listImages(): DockerApiResult
    {
        $response = $this->attempt(fn (): Response => $this->request()->get('/images/json'));

        if ($response === null || ! $response->successful()) {
            return $this->failure($response);
        }

        $images = collect($response->json())
            ->map(fn (array $image): DockerImageData => DockerImageData::fromApiResponse($image))
            ->values()
            ->all();

        return new DockerApiResult(successful: true, data: $images);
    }

    public function pullImage(string $name): DockerApiResult
    {
        // Docker's API expects `fromImage` as a query string parameter on
        // this endpoint, never a request body - passing it as the second
        // argument to post() would send it as form/JSON body instead,
        // which the real Docker Engine API silently ignores.
        return $this->resultForNoContentResponse(
            $this->attempt(fn (): Response => $this->request()->post('/images/create?'.http_build_query(['fromImage' => $name])))
        );
    }

    public function removeImage(string $id): DockerApiResult
    {
        return $this->resultForNoContentResponse(
            $this->attempt(fn (): Response => $this->request()->delete("/images/{$id}"))
        );
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('services.docker.base_url'))
            ->acceptJson()
            ->timeout(15);
    }

    /**
     * The Docker Engine API endpoint is frequently entirely unreachable (no
     * daemon running, wrong host) - a genuine network-level failure, not
     * just a non-2xx HTTP response. Laravel's HTTP client throws
     * ConnectionException for that case rather than returning a Response,
     * so every request funnels through here to turn it into an honest
     * failure result instead of an uncaught 500.
     */
    private function attempt(Closure $callback): ?Response
    {
        try {
            return $callback();
        } catch (ConnectionException $exception) {
            Log::warning('Docker API unreachable', ['message' => $exception->getMessage()]);

            return null;
        }
    }

    private function resultForNoContentResponse(?Response $response): DockerApiResult
    {
        if ($response === null || ! $response->successful()) {
            return $this->failure($response);
        }

        return new DockerApiResult(successful: true);
    }

    private function failure(?Response $response): DockerApiResult
    {
        if ($response === null) {
            return new DockerApiResult(successful: false, errors: ['Could not reach the Docker Engine API.']);
        }

        $message = $response->json('message') ?? "Docker API request failed (HTTP {$response->status()}).";

        Log::warning('Docker API request failed', ['message' => $message, 'status' => $response->status()]);

        return new DockerApiResult(successful: false, errors: [$message]);
    }
}
