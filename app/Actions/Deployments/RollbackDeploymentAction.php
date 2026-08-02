<?php

declare(strict_types=1);

namespace App\Actions\Deployments;

use App\Enums\DeploymentStatus;
use App\Enums\DeploymentTrigger;
use App\Models\Deployment;
use App\Models\User;
use App\Services\Deployments\GitDeploymentService;
use InvalidArgumentException;

class RollbackDeploymentAction
{
    public function __construct(
        private readonly GitDeploymentService $git,
    ) {}

    /**
     * Re-checks-out a previous successful deployment's exact commit, recorded
     * as its own new deployment (never mutates history) but marked
     * `RolledBack` rather than `Success` so it's visually distinct in the
     * timeline.
     */
    public function handle(Deployment $target, ?User $triggeredBy = null): Deployment
    {
        if ($target->commit_sha === null) {
            throw new InvalidArgumentException('Cannot roll back to a deployment with no recorded commit.');
        }

        $deployment = $this->git->deploy(
            $target->website,
            DeploymentTrigger::Manual,
            $triggeredBy,
            $target->commit_sha,
        );

        if ($deployment->status === DeploymentStatus::Success) {
            $deployment->update(['status' => DeploymentStatus::RolledBack]);
        }

        return $deployment->fresh();
    }
}
