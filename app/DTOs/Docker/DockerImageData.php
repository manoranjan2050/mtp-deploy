<?php

declare(strict_types=1);

namespace App\DTOs\Docker;

final readonly class DockerImageData
{
    public function __construct(
        public string $id,
        public string $tag,
        public int $sizeBytes,
    ) {}

    /**
     * @param  array<string, mixed>  $image
     */
    public static function fromApiResponse(array $image): self
    {
        return new self(
            id: $image['Id'],
            tag: $image['RepoTags'][0] ?? '<none>',
            sizeBytes: (int) $image['Size'],
        );
    }
}
