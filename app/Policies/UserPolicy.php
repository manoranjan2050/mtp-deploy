<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view users');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('view users');
    }

    public function create(User $user): bool
    {
        return $user->can('create users');
    }

    public function update(User $user, User $model): bool
    {
        if ($model->hasRole('super-admin') && ! $user->hasRole('super-admin')) {
            return false;
        }

        return $user->can('update users');
    }

    public function delete(User $user, User $model): bool
    {
        if ($model->is($user)) {
            return false;
        }

        if ($model->hasRole('super-admin')) {
            return false;
        }

        return $user->can('delete users');
    }

    public function suspend(User $user, User $model): bool
    {
        if ($model->is($user) || $model->hasRole('super-admin')) {
            return false;
        }

        return $user->can('suspend users');
    }
}
