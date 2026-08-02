<?php

declare(strict_types=1);

namespace App\Actions\Websites;

use App\DTOs\System\SystemCommandResult;
use App\Enums\WebsiteStatus;
use App\Models\Website;
use App\Services\Websites\WebsiteProvisioningService;

class SuspendWebsiteAction
{
    public function __construct(
        private readonly WebsiteProvisioningService $provisioning,
    ) {}

    public function handle(Website $website): SystemCommandResult
    {
        $website->update([
            'status' => $website->status === WebsiteStatus::Active
                ? WebsiteStatus::Suspended
                : WebsiteStatus::Active,
        ]);

        return $this->provisioning->republish($website->fresh());
    }
}
