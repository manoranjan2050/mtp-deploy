<?php

declare(strict_types=1);

namespace App\Actions\Websites;

use App\Models\Website;
use App\Services\Websites\WebsiteProvisioningService;

class DeleteWebsiteAction
{
    public function __construct(
        private readonly WebsiteProvisioningService $provisioning,
    ) {}

    public function handle(Website $website): void
    {
        $this->provisioning->deprovision($website);

        $website->delete();
    }
}
