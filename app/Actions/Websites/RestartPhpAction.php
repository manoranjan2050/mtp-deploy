<?php

declare(strict_types=1);

namespace App\Actions\Websites;

use App\DTOs\System\SystemCommandResult;
use App\Models\Website;
use App\Services\Websites\WebsiteProvisioningService;

class RestartPhpAction
{
    public function __construct(
        private readonly WebsiteProvisioningService $provisioning,
    ) {}

    public function handle(Website $website): SystemCommandResult
    {
        return $this->provisioning->restartPhpFpm($website->php_version);
    }
}
