<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\Auth\AssignSuperAdminRoleOnFirstRegistration;
use App\Listeners\Auth\RecordLastLogin;
use App\Models\Database;
use App\Models\DatabaseUser;
use App\Models\Deployment;
use App\Models\Server;
use App\Models\User;
use App\Models\Website;
use App\Policies\ActivityPolicy;
use App\Policies\DatabasePolicy;
use App\Policies\DatabaseUserPolicy;
use App\Policies\DeploymentPolicy;
use App\Policies\RolePolicy;
use App\Policies\ServerPolicy;
use App\Policies\UserPolicy;
use App\Policies\WebsitePolicy;
use Filament\Auth\Events\Registered;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        Gate::policy(Website::class, WebsitePolicy::class);
        Gate::policy(Database::class, DatabasePolicy::class);
        Gate::policy(DatabaseUser::class, DatabaseUserPolicy::class);
        Gate::policy(Deployment::class, DeploymentPolicy::class);
        Gate::policy(Server::class, ServerPolicy::class);

        Gate::before(fn (User $user, string $ability): ?true => $user->hasRole('super-admin') ? true : null);

        // Module 17's API: a generous default per-token limit, and a much
        // stricter one on deploy-triggering endpoints specifically - see
        // docs/API.md and docs/Security.md.
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('deploy-api', fn (Request $request): Limit => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
    }
}
