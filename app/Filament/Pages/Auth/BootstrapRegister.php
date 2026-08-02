<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Auth\Pages\Register;
use Filament\Facades\Filament;

/**
 * Registration is only reachable while the users table is empty (initial
 * super-admin bootstrap). Once at least one user exists, this page redirects
 * to login - new users are created via admin invite from that point on.
 *
 * @see AdminPanelProvider
 */
class BootstrapRegister extends Register
{
    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());

            return;
        }

        if (User::query()->exists()) {
            redirect()->to(Filament::getLoginUrl());

            return;
        }

        parent::mount();
    }
}
