<?php

declare(strict_types=1);

namespace App\DTOs\Websites;

use App\Enums\WebsiteFramework;

final readonly class CreateWebsiteData
{
    /**
     * @param  list<string>  $aliases
     */
    public function __construct(
        public int $serverId,
        public string $name,
        public string $domain,
        public string $phpVersion,
        public WebsiteFramework $framework,
        public array $aliases = [],
        public ?int $createdBy = null,
    ) {}
}
