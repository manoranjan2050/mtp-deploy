<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Deployment;
use App\Models\User;

/**
 * A deployment's authorization is entirely derived from its website's -
 * anyone who can update the website can trigger/view/roll back its
 * deployments. No separate deployment-specific permission; see WebsitePolicy.
 */
class DeploymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view websites');
    }

    public function view(User $user, Deployment $deployment): bool
    {
        return $user->can('update', $deployment->website);
    }

    public function create(User $user): bool
    {
        return $user->can('update websites');
    }

    public function deploy(User $user, Deployment $deployment): bool
    {
        return $user->can('update', $deployment->website);
    }

    public function rollback(User $user, Deployment $deployment): bool
    {
        return $user->can('update', $deployment->website);
    }
}
