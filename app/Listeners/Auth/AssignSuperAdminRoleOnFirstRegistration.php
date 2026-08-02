<?php

declare(strict_types=1);

namespace App\Listeners\Auth;

use App\Models\User;
use Filament\Auth\Events\Registered;
use Spatie\Permission\Models\Role;

class AssignSuperAdminRoleOnFirstRegistration
{
    /**
     * BootstrapRegister already redirects away once any user exists, but that
     * is a single page-level guard - this listener independently re-checks
     * for an existing super-admin so a bypass of that guard (a race, a bug,
     * a future refactor) can never mint a second one. Defense in depth per
     * docs/Security.md.
     */
    public function handle(Registered $event): void
    {
        $user = $event->getUser();

        if (! $user instanceof User) {
            return;
        }

        $superAdminAlreadyExists = User::query()
            ->whereKeyNot($user->getKey())
            ->whereHas('roles', fn ($query) => $query->where('name', 'super-admin'))
            ->exists();

        if ($superAdminAlreadyExists) {
            return;
        }

        $user->assignRole(Role::findOrCreate('super-admin', 'web'));
    }
}
