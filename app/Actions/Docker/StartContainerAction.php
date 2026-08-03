<?php

declare(strict_types=1);

namespace App\Actions\Docker;

use App\DTOs\Docker\DockerApiResult;
use App\Services\Docker\DockerApiClient;

class StartContainerAction
{
    public function __construct(
        private readonly DockerApiClient $docker,
    ) {}

    public function handle(string $containerId): DockerApiResult
    {
        $result = $this->docker->startContainer($containerId);

        activity('docker')
            ->causedBy(auth()->user())
            ->withProperties(['container_id' => $containerId, 'successful' => $result->successful])
            ->log('started container');

        return $result;
    }
}
