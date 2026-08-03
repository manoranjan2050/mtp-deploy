<?php

declare(strict_types=1);

namespace App\Actions\Docker;

use App\DTOs\Docker\DockerApiResult;
use App\Services\Docker\DockerApiClient;

class RemoveImageAction
{
    public function __construct(
        private readonly DockerApiClient $docker,
    ) {}

    public function handle(string $imageId): DockerApiResult
    {
        $result = $this->docker->removeImage($imageId);

        activity('docker')
            ->causedBy(auth()->user())
            ->withProperties(['image_id' => $imageId, 'successful' => $result->successful])
            ->log('removed image');

        return $result;
    }
}
