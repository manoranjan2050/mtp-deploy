<?php

declare(strict_types=1);

namespace App\Actions\Docker;

use App\DTOs\Docker\DockerApiResult;
use App\Services\Docker\DockerApiClient;

class StopContainerAction
{
    public function __construct(
        private readonly DockerApiClient $docker,
    ) {}

    public function handle(string $containerId): DockerApiResult
    {
        $result = $this->docker->stopContainer($containerId);

        activity('docker')
            ->causedBy(auth()->user())
            ->withProperties(['container_id' => $containerId, 'successful' => $result->successful])
            ->log('stopped container');

        return $result;
    }
}
