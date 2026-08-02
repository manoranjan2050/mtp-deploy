<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DatabaseUser;
use App\Models\User;

/**
 * Database *users* (MySQL accounts) are a broader security lever than a
 * single database - a compromised or over-privileged one can potentially see
 * across databases. Every mutation is gated behind `manage database
 * privileges`, not the more permissive `create/delete databases` a developer
 * holds - see docs/Security.md and RoleSeeder.
 */
class DatabaseUserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view databases');
    }

    public function view(User $user, DatabaseUser $databaseUser): bool
    {
        return $user->can('view databases');
    }

    public function create(User $user): bool
    {
        return $user->can('manage database privileges');
    }

    public function update(User $user, DatabaseUser $databaseUser): bool
    {
        return $user->can('manage database privileges');
    }

    public function delete(User $user, DatabaseUser $databaseUser): bool
    {
        return $user->can('manage database privileges');
    }
}
