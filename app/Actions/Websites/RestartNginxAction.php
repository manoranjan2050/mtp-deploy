<?php

declare(strict_types=1);

namespace App\Actions\Websites;

use App\DTOs\System\SystemCommandResult;
use App\Services\Websites\WebsiteProvisioningService;

class RestartNginxAction
{
    public function __construct(
        private readonly WebsiteProvisioningService $provisioning,
    ) {}

    public function handle(): SystemCommandResult
    {
        return $this->provisioning->reloadNginx();
    }
}
