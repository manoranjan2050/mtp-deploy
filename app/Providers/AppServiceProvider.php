<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\Auth\AssignSuperAdminRoleOnFirstRegistration;
use App\Listeners\Auth\RecordLastLogin;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Filament\Auth\Events\Registered;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * Laravel 12's skeleton has no EventServiceProvider auto-discovery, so every
     * listener is registered explicitly here - see docs/CodingStandards.md.
     */
    public function boot(): void
    {
        Event::listen(Registered::class, AssignSuperAdminRoleOnFirstRegistration::class);
        Event::listen(Login::class, RecordLastLogin::class);

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Activity::class, ActivityPolicy::class);

        Gate::before(fn (User $user, string $ability): ?true => $user->hasRole('super-admin') ? true : null);
    }
}
