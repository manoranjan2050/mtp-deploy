<?php

declare(strict_types=1);

namespace App\Actions\Websites;

use App\DTOs\System\SystemCommandResult;
use App\DTOs\Websites\CreateWebsiteData;
use App\Models\Website;
use App\Services\Websites\WebsiteProvisioningService;

class CreateWebsiteAction
{
    public function __construct(
        private readonly WebsiteProvisioningService $provisioning,
    ) {}

    /**
     * @return array{website: Website, provisioning: SystemCommandResult}
     */
    public function handle(CreateWebsiteData $data): array
    {
        $website = Website::query()->create([
            'server_id' => $data->serverId,
            'name' => $data->name,
            'domain' => $data->domain,
            'aliases' => $data->aliases,
            'document_root' => rtrim(config('mtp.sites_root'), '/')."/{$data->domain}",
            'php_version' => $data->phpVersion,
            'framework' => $data->framework,
            'created_by' => $data->createdBy,
        ]);

        $result = $this->provisioning->provision($website);

        return ['website' => $website, 'provisioning' => $result];
    }
}
