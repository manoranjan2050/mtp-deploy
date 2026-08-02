<?php

declare(strict_types=1);

namespace App\Actions\Websites;

use App\DTOs\System\SystemCommandResult;
use App\Models\Website;
use App\Services\Websites\WebsiteProvisioningService;

class ChangePhpVersionAction
{
    public function __construct(
        private readonly WebsiteProvisioningService $provisioning,
    ) {}

    public function handle(Website $website, string $phpVersion): SystemCommandResult
    {
        $website->update(['php_version' => $phpVersion]);

        return $this->provisioning->republish($website->fresh());
    }
}
