<?php

declare(strict_types=1);

namespace App\Actions\Docker;

use App\DTOs\Docker\DockerApiResult;
use App\Services\Docker\DockerApiClient;

class PullImageAction
{
    public function __construct(
        private readonly DockerApiClient $docker,
    ) {}

    public function handle(string $imageName): DockerApiResult
    {
        $result = $this->docker->pullImage($imageName);

        activity('docker')
            ->causedBy(auth()->user())
            ->withProperties(['image' => $imageName, 'successful' => $result->successful])
            ->log('pulled image');

        return $result;
    }
}
