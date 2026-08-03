<?php

declare(strict_types=1);

namespace App\DTOs\Docker;

final readonly class DockerContainerData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $image,
        public string $state,
        public string $status,
    ) {}

    /**
     * @param  array<string, mixed>  $container
     */
    public static function fromApiResponse(array $container): self
    {
        return new self(
            id: $container['Id'],
            name: ltrim($container['Names'][0] ?? $container['Id'], '/'),
            image: $container['Image'],
            state: $container['State'],
            status: $container['Status'],
        );
    }
}
