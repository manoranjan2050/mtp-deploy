<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Database;
use App\Models\User;

class DatabasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view databases');
    }

    public function view(User $user, Database $database): bool
    {
        return $this->canManage($user, $database, 'view databases');
    }

    public function create(User $user): bool
    {
        return $user->can('create databases');
    }

    public function delete(User $user, Database $database): bool
    {
        return $this->canManage($user, $database, 'delete databases');
    }

    public function managePrivileges(User $user, Database $database): bool
    {
        return $user->can('manage database privileges');
    }

    /**
     * Same scoping rule as WebsitePolicy: a developer only manages databases
     * attached to a website they created.
     */
    private function canManage(User $user, Database $database, string $permission): bool
    {
        if (! $user->can($permission)) {
            return false;
        }

        if ($user->hasRole('developer')) {
            return $database->website?->created_by === $user->id;
        }

        return true;
    }
}
