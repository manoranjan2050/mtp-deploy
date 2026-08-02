<?php

declare(strict_types=1);

namespace App\Actions\Deployments;

use App\Enums\DeploymentTrigger;
use App\Models\Deployment;
use App\Models\User;
use App\Models\Website;
use App\Services\Deployments\GitDeploymentService;

class TriggerDeploymentAction
{
    public function __construct(
        private readonly GitDeploymentService $git,
    ) {}

    public function handle(Website $website, DeploymentTrigger $trigger, ?User $triggeredBy = null): Deployment
    {
        return $this->git->deploy($website, $trigger, $triggeredBy);
    }
}
