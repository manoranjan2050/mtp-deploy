<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Website;

class WebsitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view websites');
    }

    public function view(User $user, Website $website): bool
    {
        return $this->canManage($user, $website, 'view websites');
    }

    public function create(User $user): bool
    {
        return $user->can('create websites');
    }

    public function update(User $user, Website $website): bool
    {
        return $this->canManage($user, $website, 'update websites');
    }

    public function delete(User $user, Website $website): bool
    {
        return $this->canManage($user, $website, 'delete websites');
    }

    public function suspend(User $user, Website $website): bool
    {
        return $this->canManage($user, $website, 'update websites');
    }

    public function manageServices(User $user, Website $website): bool
    {
        return $user->can('manage website services');
    }

    public function manageFiles(User $user, Website $website): bool
    {
        return $this->canManage($user, $website, 'manage website files');
    }

    /**
     * Admins/super-admins manage every website; a `developer` only manages
     * websites they created - see the RoleSeeder comment on why "assigned
     * sites" is simplified to "created_by = self" for now.
     */
    private function canManage(User $user, Website $website, string $permission): bool
    {
        if (! $user->can($permission)) {
            return false;
        }

        if ($user->hasRole('developer')) {
            return $website->created_by === $user->id;
        }

        return true;
    }
}
