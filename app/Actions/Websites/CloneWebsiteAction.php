<?php

declare(strict_types=1);

namespace App\Actions\Websites;

use App\DTOs\System\SystemCommandResult;
use App\Models\Website;
use App\Services\Websites\WebsiteProvisioningService;
use Illuminate\Support\Facades\File;

class CloneWebsiteAction
{
    public function __construct(
        private readonly WebsiteProvisioningService $provisioning,
    ) {}

    /**
     * @return array{website: Website, provisioning: SystemCommandResult}
     */
    public function handle(Website $source, string $newDomain, ?int $createdBy = null): array
    {
        $newDocumentRoot = rtrim(config('mtp.sites_root'), '/')."/{$newDomain}";

        $clone = Website::query()->create([
            'server_id' => $source->server_id,
            'name' => "{$source->name} (clone)",
            'domain' => $newDomain,
            'aliases' => [],
            'document_root' => $newDocumentRoot,
            'php_version' => $source->php_version,
            'framework' => $source->framework,
            'created_by' => $createdBy,
        ]);

        if (File::isDirectory($source->document_root)) {
            File::ensureDirectoryExists($newDocumentRoot);
            File::copyDirectory($source->document_root, $newDocumentRoot);
        }

        $result = $this->provisioning->provision($clone);

        return ['website' => $clone, 'provisioning' => $result];
    }
}
