# TODO — MTP Deploy

Live, granular checklist. High-level module order lives in
[docs/Roadmap.md](docs/Roadmap.md); full feature list lives in
[docs/Features.md](docs/Features.md). This file tracks exactly what's in flight
right now so a new session (human or AI) can pick up without re-deriving state.

## Done — Scaffold
- [x] Repo scaffolded: `composer create-project laravel/laravel MTPDeploy "12.*"`
- [x] Local MySQL 8.0.46 (AMPPS, MariaDB-protocol compatible) started; dedicated
      `mtpdeploy`@`127.0.0.1`/`localhost` app user created (not root) with its own
      `mtpdeploy` database
- [x] All 10 architecture docs written in `docs/`, plus README.md, TODO.md, CLAUDE.md

## Done — Module 1: Authentication

**Stack deviation from the original spec, both for real reasons - see
docs/Architecture.md and CLAUDE.md for the full explanation:**
- PHP 8.2.31 is what's installed (AMPPS), not 8.4+.
- **Filament v5.7 + Livewire v4.3**, not "Filament v4 / Livewire 3": every published
  Livewire 3.x release is blocked by Composer's security-advisory policy
  (CVE-2025-54068 RCE + two related CVEs, unpatched in any 3.x tag). Filament v5
  requires Livewire ^4.1, which is patched, and is the direct successor to
  Filament v4 (same author, same panel APIs).
- **2FA uses Filament's own built-in Multi-Factor Authentication (App/TOTP
  provider)**, not a hand-rolled `pragmarx/google2fa-laravel` integration -
  Filament v4/v5 ships this natively with QR enrollment, recovery codes, and an
  `isRequired` closure, so a custom implementation would have been strictly worse
  and less maintained.

### Packages installed
- [x] `livewire/livewire` (^4.3, pulled in by Filament)
- [x] `filament/filament` (^5.7)
- [x] `spatie/laravel-permission` (^6.25)
- [x] `spatie/laravel-activitylog` (^4.12)
- [x] `laravel/sanctum` (^4.x - not bundled by the Laravel 12 skeleton by default,
      installed explicitly)

### Schema
- [x] `users` migration extended: `app_authentication_secret`,
      `app_authentication_recovery_codes` (Filament's native MFA column names),
      `is_active`, `last_login_at`, `last_login_ip`, `softDeletes()`
- [x] Published + ran spatie/permission and spatie/activitylog migrations
- [x] `app/Models/Session.php` - read-only Eloquent wrapper over the `sessions`
      table (Filament's table component requires an Eloquent query, not a raw
      query builder)
- [x] `RoleSeeder` (super-admin, admin, developer, viewer) + `PermissionSeeder`
      (10 permissions covering users/roles/activity-log)
- [x] `DatabaseSeeder` creates a default super-admin only in `local`/`testing` env

### Backend
- [x] `User` model: `HasRoles`, `LogsActivity`, `HasApiTokens`, `SoftDeletes`,
      Filament's `FilamentUser` + MFA contracts/traits
- [x] `Actions/Auth/CreateApiTokenAction`, `RevokeSessionAction`
- [x] `Policies/UserPolicy`, `RolePolicy`, `ActivityPolicy` - registered explicitly
      via `Gate::policy()` in `AppServiceProvider::boot()`
- [x] `Listeners/Auth/AssignSuperAdminRoleOnFirstRegistration`,
      `RecordLastLogin` - registered explicitly via `Event::listen()`
      (Laravel 12 has no auto-discovery — see docs/CodingStandards.md)
- [x] `Gate::before()` super-admin bypass

### Filament / UI
- [x] Filament `admin` panel installed
- [x] Login (Filament built-in - rate-limited, 2FA challenge step included)
- [x] `BootstrapRegister` (custom): registration only reachable while zero users
      exist, then redirects to login; first registrant auto-assigned super-admin
- [x] Forgot Password / Reset Password (Filament built-in)
- [x] Profile page (Filament built-in `EditProfile`): name/email/password + MFA
      enroll/disable/recovery-codes actions built into the page header
- [x] Profile → Sessions manager (`app/Filament/Pages/Profile/Sessions.php`):
      list + revoke one + revoke others, "this device" indicator
- [x] Profile → API Tokens manager (`.../Profile/ApiTokens.php`): create with
      ability scopes (`App\Enums\ApiTokenAbility`), list, revoke
- [x] `UserResource`: role assignment, suspend/reinstate action, soft-delete filter
- [x] `RoleResource`: permission checkbox matrix
- [x] `ActivityLogResource`: read-only (no create/edit/delete), filter by event
- [x] Dark mode (Filament built-in, present in the topbar)

### Tests (`tests/Feature/Auth/`, 22 passing)
- [x] Login: happy path, wrong password, suspended user blocked, last-login
      recorded
- [x] Register: first user becomes super-admin, page redirects once a user
      exists, listener independently refuses a second super-admin (defense in
      depth even if the page-level guard were bypassed)
- [x] Roles/permissions: developer/viewer denied, admin scoped correctly, admin
      cannot touch a super-admin, no self-suspend, super-admin bypass works
- [x] Sessions: revoke own, cannot revoke another user's, revoke-others keeps
      current
- [x] API tokens: ability scoping enforced, revoke deletes the row
- [x] Activity log: entries written for user create/update and token creation
- [ ] **Not covered by an automated test** (relying on Filament's own upstream
      test suite instead, since these are vendor-built flows we didn't write):
      forgot/reset password email flow, MFA enroll/confirm/recovery-code UI

### Wrap-up
- [x] `php artisan test` green (22/22)
- [x] `vendor/bin/pint` clean
- [x] Manually smoke-tested in browser: login, Users/Roles/Sessions/API
      Tokens/Activity Log pages all render and function (found and fixed one bug
      this way - Sessions page needed an Eloquent model, not a raw query builder;
      also caught and fixed a bad `formatStateUsing` on the API Tokens abilities
      badge column that only surfaced once a real token existed)
- [x] `docs/Database.md` Module 1 section - reviewed, matches implementation
      (column names updated to Filament's MFA convention)
- [x] `docs/Features.md` Module 1 checklist ticked
- [x] `docs/Roadmap.md` Module 1 status → ✅

## Done — Module 2: Dashboard

### Backend
- [x] `system_metric_snapshots` migration + `App\Models\SystemMetricSnapshot`
      (no `updated_at` - append-only, `recorded_at` is the timestamp of record)
- [x] `App\Enums\ServiceStatus` (Running/Stopped/Unavailable - "Unavailable" is a
      distinct, honest state, never conflated with "Stopped")
- [x] `App\DTOs\System\SystemMetricsData` (readonly, `::unsupported()` factory)
- [x] `App\Services\System\SystemMetricsService`: reads `/proc/stat`,
      `/proc/meminfo`, `/proc/net/dev`, `sys_getloadavg()`, `disk_*_space()` on
      Linux; returns `SystemMetricsData::unsupported()` on any other OS (this dev
      machine is Windows) rather than fabricating zeros
- [x] `App\Services\System\ServiceStatusService`: real connectivity probes for
      MariaDB (`DB::connection()->getPdo()`) and Redis (`Redis::ping()`, both
      cross-platform); `pgrep`-based process check for nginx/cloudflared
      (Linux-only, reports `Unavailable` elsewhere)
- [x] `app:capture-system-metrics` console command, scheduled `everyMinute()` in
      `routes/console.php`

### Filament / UI
- [x] `SystemStatsOverview` (StatsOverviewWidget): CPU/RAM/disk/load-average tiles,
      live capture per page load, honest "Unavailable" state off-Linux
- [x] `ServiceStatusWidget`: PHP version + MariaDB/Redis/nginx/Cloudflare Tunnel
      badges
- [x] `MetricsTrendChart` (ChartWidget/Chart.js): CPU% + Memory% over the last 60
      snapshots
- [x] `LatestDeploymentsWidget`: honest empty-state placeholder (real data is
      Module 5's job)
- [x] Removed Filament's generic branding widget (`FilamentInfoWidget`) from the
      panel, kept `AccountWidget` (welcome message)

### Tests (`tests/Feature/Dashboard/`, `tests/Unit/Services/System/`, 11 new)
- [x] Each widget renders successfully (Livewire component tests, not browser
      screenshots - this session's browser pane isn't visually composited, so
      Alpine's viewport-based lazy-load (`x-intersect`) never fires there; that's
      an automation-environment limitation, not confirmed by a server error, and
      the dashboard route itself does return 200 in a feature test)
- [x] `MetricsTrendChart` produces correct CPU%/Memory% series from real snapshot
      rows
- [x] `SystemMetricsService`/`ServiceStatusService` unit tests, OS-conditional
      (skip the Linux-only assertions on this Windows dev box, run the
      non-Linux/"Unavailable" assertions instead)

### Bug found via this module and fixed in Module 1's code
- [x] `User::canAccessPanel()` read `$this->is_active` and could return `null`
      (not `bool`) for a freshly-created, unrefreshed `User` instance - Eloquent
      doesn't hydrate DB-applied column defaults onto an in-memory model after
      `create()`. Fixed with an in-memory `protected $attributes = ['is_active'
      => true]` default matching the migration's own default. Caught by a new
      Dashboard test (`test_dashboard_page_loads_for_an_authenticated_user`)
      using a plain `User::factory()->create()`, not one of Module 1's own tests
      which happened to always set `is_active` explicitly.

### Wrap-up
- [x] `php artisan test` green (33 passed, 1 skipped - Linux-only)
- [x] `vendor/bin/pint` clean
- [x] `docs/Database.md`, `docs/Features.md`, `docs/Roadmap.md` updated

## Done — Module 3: Website Manager

### Schema
- [x] `servers` migration + `App\Models\Server` (`is_local` singleton, seeded by
      `ServerSeeder`; `availablePhpVersions()` falls back to `['8.2','8.3','8.4']`)
- [x] `websites` migration + `App\Models\Website` (soft deletes, `LogsActivity`)
- [x] `App\Enums\ServerStatus`, `WebsiteStatus`, `WebsiteFramework`, `SslStatus` -
      all implement Filament's `HasLabel`/`HasColor` contracts so
      `->options(Enum::class)` and `->badge()` render correctly without manual
      `->color()`/label-mapping closures (see the bug below for what happens
      when you forget this)
- [x] `config/mtp.php`: `nginx_sites_available_path`, `nginx_sites_enabled_path`,
      `sites_root` - overridable so tests (and this Windows dev box) never touch
      real system paths

### Backend
- [x] `App\Enums\WhitelistedOperation` - the fixed, closed set of shell operations
      (`ReloadNginx`, `TestNginxConfig`, `RestartPhpFpm`) the app is ever allowed
      to run; adding a new privileged capability means adding a new case here,
      not a new ad-hoc `Process` call somewhere
- [x] `App\Services\System\SystemCommandService` - the *only* class allowed to
      construct a `Symfony\Process`; logs before/after via the activity log
      (`system-command` log name) per docs/Security.md
- [x] `App\Services\Websites\NginxConfigGeneratorService` - pure string
      generation (no I/O), dispatches on `Website::status` so a suspended site
      always gets a 503 block, never accidentally the live config
- [x] `App\Services\Websites\WebsiteProvisioningService` - the I/O side:
      writes/removes the vhost file + symlink, creates the document root,
      calls `SystemCommandService` to test+reload nginx
- [x] Actions: `CreateWebsiteAction`, `DeleteWebsiteAction`, `SuspendWebsiteAction`,
      `CloneWebsiteAction`, `ChangePhpVersionAction`, `ToggleSslAction`,
      `RestartPhpAction`, `RestartNginxAction`
- [x] `WebsitePolicy` - admin/super-admin manage every site; `developer` only
      manages sites they created (`created_by = self` - "assigned sites" per
      docs/Security.md simplified for now, see `RoleSeeder` comment); `viewer`
      read-only; `manageServices` (restart nginx/PHP) gated separately since
      restarting nginx affects every site on the server
- [x] 5 new permissions (`view/create/update/delete websites`,
      `manage website services`), wired into `admin`/`developer`/`viewer` roles

### Filament / UI
- [x] `WebsiteResource`: create/edit/list, developer-scoped `getEloquentQuery()`
      (defense in depth alongside the policy - a developer doesn't just get
      blocked from acting on others' sites, they don't see them in the list)
- [x] Custom `CreateWebsite`/`EditWebsite` pages that call the Actions/
      provisioning service instead of raw Eloquent create/update, so every save
      keeps the on-disk vhost in sync with the DB record
- [x] Table actions: Suspend/Reinstate, Clone (new-domain modal), Change PHP
      version, Enable/Disable SSL, Restart PHP (per-site), Restart nginx
      (header action, server-wide)
- [x] Domain/framework locked on edit (changing either needs delete + recreate -
      not yet supported); document root shown read-only, never hand-entered

### Tests (`tests/Feature/Websites/`, `tests/Unit/Services/Websites/`, 21 new)
- [x] `NginxConfigGeneratorService`: active/suspended/Laravel-vs-static output,
      fully deterministic (no I/O)
- [x] `WebsiteProvisioningService`: real file writes/symlinks/deletes against a
      temp directory (never the real `/etc/nginx`) - provision, deprovision,
      republish
- [x] All 8 website Actions, including file-copy verification for Clone
- [x] `WebsitePolicy`: developer scoping (own sites only), viewer read-only,
      admin full access, `manageServices` restricted to admin/super-admin
- [x] `CreateWebsitePageTest` - exercises the **real Filament page** via
      `Livewire::test()`, not just the Action directly (see the bug below -
      this is what actually caught it)

### Bugs found via this module and fixed
- [x] Same `is_active`-class bug as Module 2, now on `Website`:
      `status`/`ssl_status`/`framework` all have DB defaults that don't hydrate
      onto a freshly-created, unrefreshed instance, so
      `NginxConfigGeneratorService`'s `match ($website->status)` threw
      `UnhandledMatchError`. Fixed with an in-memory `$attributes` default on
      `Website`, same pattern as `User`. **This is now a recurring class of bug
      in this project - check every new model with a DB column default.**
- [x] `CreateWebsite::handleRecordCreation()` called
      `WebsiteFramework::from($data['framework'])`, but Filament's `Select`
      already casts the form value to the enum instance when
      `->options(EnumClass::class)` is used - calling `::from()` again threw a
      `TypeError`. Only caught once a test exercised the real Filament page
      (`CreateWebsitePageTest`) rather than calling `CreateWebsiteAction`
      directly - a reminder that Action-level tests alone don't cover
      Filament's own data-casting behavior.
- [x] Enum options (`WebsiteFramework`, etc.) rendered raw case names
      ("PlainPhp") in Filament `Select`/`badge()` fields until each enum
      implemented `Filament\Support\Contracts\HasLabel`/`HasColor` - a custom
      `label()`/`color()` method alone is invisible to Filament's automatic
      enum integration; it must be `getLabel()`/`getColor()` matching the
      interface. Retrofitted onto all pre-existing enums too (`ServerStatus`,
      `ServiceStatus` from Module 2).

### Wrap-up
- [x] `php artisan test` green (54 passed, 1 skipped - Linux-only)
- [x] `vendor/bin/pint` clean
- [x] Manually verified in browser: full create-website flow end to end,
      including a real generated nginx vhost file written to disk
- [x] `docs/Database.md`, `docs/Features.md`, `docs/Roadmap.md` updated

## Done — Module 4: Database Manager

### Setup
- [x] A dedicated `mtpdeploy` app DB user (Module 1) is scoped to its own
      database only - Module 4 needed a separate, more-privileged connection
      to provision/drop *other* databases. Granted `root@127.0.0.1` (matching
      the existing `root@localhost`) and added a `mysql_admin` connection
      (`config/database.php`, `DB_ADMIN_*` env vars) used only by
      `DatabaseManagerService`/`DatabaseBackupService`.
- [x] `MYSQLDUMP_PATH`/`MYSQL_CLI_PATH` env vars point at the real AMPPS
      `mysqldump.exe`/`mysql.exe` on this machine.

### Schema
- [x] `databases`, `database_users`, `database_user_database` (pivot) migrations
- [x] `App\Models\Database`, `DatabaseUser`, and a dedicated
      `DatabaseUserDatabase` Pivot model (needed for the pivot's `privileges`
      JSON column to actually cast - see the bug below)
- [x] `App\Enums\DatabasePrivilege` (SELECT/INSERT/UPDATE/DELETE/CREATE/DROP/
      ALTER/INDEX/ALL PRIVILEGES - a deliberate subset, nothing like FILE/SUPER
      that reaches outside the granted database), `App\Enums\DatabaseStatus`

### Backend
- [x] `App\Services\Databases\DatabaseManagerService` - real
      `CREATE`/`DROP DATABASE`, `CREATE`/`DROP USER`, `GRANT`/`REVOKE` against
      the `mysql_admin` connection; every identifier (db/user names) validated
      against a strict allowlist pattern before touching SQL, since PDO can't
      bind identifiers in DDL
- [x] `App\Services\Databases\DatabaseBackupService` - real `mysqldump`/`mysql`
      client invocations via `Symfony\Process`, credentials passed through a
      temporary `--defaults-extra-file` (never as a CLI arg visible to other
      processes), deleted immediately after use
- [x] Actions: `CreateDatabaseAction` (only creates the metadata row if the real
      `CREATE DATABASE` succeeds - unlike Website, a phantom database record
      would be actively misleading), `DeleteDatabaseAction`,
      `CreateDatabaseUserAction`, `DeleteDatabaseUserAction`,
      `UpdatePrivilegesAction`, `BackupDatabaseAction`, `RestoreDatabaseAction`
- [x] `DatabasePolicy` (developer scoped to databases on their own websites,
      same pattern as `WebsitePolicy`), `DatabaseUserPolicy` (every mutation
      gated behind `manage database privileges` - a DB user account is a
      broader security lever than one database, admin/super-admin only)
- [x] 4 new permissions (`view/create/delete databases`,
      `manage database privileges`), wired into roles

### Filament / UI
- [x] `DatabaseResource`: list + create only (no Edit - a database's
      name/charset/collation aren't meaningfully editable after creation),
      developer-scoped `getEloquentQuery()` via the linked website's
      `created_by`
- [x] Table actions: Backup (downloads nothing yet, just confirms the file was
      written server-side), Restore (file upload → real `mysql` client restore,
      uploaded temp file deleted after), Manage privileges (user + privilege
      checklist modal), Delete (drops the real database first)
- [x] `DatabaseUserResource`: list + create only (a MySQL password can't be
      safely round-tripped through an edit form once set), shows each user's
      linked databases as badges, Delete drops the real MySQL account

### Tests (`tests/Unit/Services/Databases/`, `tests/Feature/Databases/`, 15 new)
- [x] `DatabaseManagerServiceTest` - **real** `CREATE`/`DROP DATABASE`,
      `CREATE`/`DROP USER`, `GRANT`/`REVOKE` against this dev machine's actual
      local MySQL (not mocked), uniquely-named throwaway db/user per test,
      cleaned up in `tearDown()`
- [x] `DatabaseBackupServiceTest` - a genuine `mysqldump` → data loss →
      `mysql`-client restore round trip, asserts the restored row is back
- [x] `DatabaseActionsTest`, `DatabasePolicyTest` - Action orchestration and
      authorization scoping, same patterns as Modules 1 and 3

### Bugs found via this module and fixed
- [x] `DatabaseManagerService` originally tried to bind the `host` part of
      `` `user`@? `` as a PDO parameter - MySQL's driver reads `@?` as a
      user-defined-variable reference, not "at symbol then placeholder,"
      producing a syntax error with the `?` left completely unsubstituted.
      Fixed by validating `host` against a strict allowlist pattern and
      interpolating it directly instead of binding it.
- [x] Even after fixing that, `CREATE USER ... IDENTIFIED BY ?` **still**
      failed - MySQL's PDO driver does not support bound parameters in
      `CREATE USER` at all (it isn't a preparable DML statement). Fixed with
      `PDO::quote()` for safe manual interpolation of the password instead of
      parameter binding. Both of these were only found by running the real
      statements against this dev machine's actual MySQL - a mocked/faked
      `DB::statement()` call in a test would never have caught either.
- [x] `$user->databases()->syncWithoutDetaching([...['privileges' => [...]]])`
      failed with "Array to string conversion" - Eloquent does not auto-cast
      pivot table attributes on the default anonymous pivot. Fixed by creating
      a dedicated `App\Models\DatabaseUserDatabase` Pivot model with its own
      `casts()` and wiring `->using(DatabaseUserDatabase::class)` into both
      sides of the relationship.

### Wrap-up
- [x] `php artisan test` green (69 passed, 1 skipped - Linux-only)
- [x] `vendor/bin/pint` clean
- [x] Manually verified in browser: created a real database through the panel,
      confirmed it exists via the MySQL CLI, cleaned up afterward
- [x] `docs/Database.md`, `docs/Features.md`, `docs/Roadmap.md` updated

## Done — Module 5: Deployment

### Schema
- [x] `websites` gets `repository_url`, `git_branch` (default `main`),
      `webhook_token` (unique, auto-generated on create), `auto_deploy`
- [x] `deployments` migration + `App\Models\Deployment` (`appendLog()` helper,
      no soft deletes - history is append-only)
- [x] `App\Enums\DeploymentProvider`, `DeploymentStatus`, `DeploymentTrigger`

### Backend
- [x] `App\Services\Deployments\GitDeploymentService` - real `git`
      clone/fetch/reset/rev-parse against `Website::document_root`; in tests,
      `repository_url` points at a real local bare git repo fixture, not a mock
- [x] `TriggerDeploymentAction`, `RollbackDeploymentAction` (re-deploys the
      target's exact commit as a *new* deployment, marked `RolledBack` rather
      than mutating history)
- [x] `App\Http\Controllers\DeploymentWebhookController` - the one legitimate
      plain-Controller use per docs/FolderStructure.md; per-website token in
      the URL + optional GitHub-style `X-Hub-Signature-256` HMAC verification
- [x] New `routes/api.php` (Laravel 12's skeleton only wires `web` by
      default), registered in `bootstrap/app.php` - API routes skip CSRF,
      which the webhook needs since GitHub can't send our CSRF token
- [x] `DeploymentPolicy` - entirely derived from the parent website's policy
      (anyone who can update the website can deploy/roll back it)

### Filament / UI
- [x] `WebsiteForm`'s new "Deployment" section (edit-only): repository URL,
      branch, auto-deploy toggle, read-only webhook URL
- [x] `WebsitesTable`'s Deploy action (visible only once a repository URL is set)
- [x] `DeploymentResource` - read-only (no create/edit, same reasoning as
      `DatabaseResource`/`DatabaseUserResource`: nothing to hand-edit), with a
      Rollback row action gated to `Success` deployments only

### Tests (`tests/Feature/Deployments/`, 18 new)
- [x] `GitDeploymentServiceTest` - a **real** clone → deploy → second-commit
      deploy → rollback cycle against a local bare git repo fixture, asserting
      on actual file contents at each step, not mocked
- [x] `DeploymentWebhookControllerTest` - unknown token (404), auto-deploy
      disabled (403), wrong signature (403), valid/absent signature (200 +
      deployment row created)
- [x] `DeploymentPolicyTest` - same developer/viewer/admin scoping pattern as
      Website and Database
- [x] `ListWebsitesPageTest` - renders the real Websites list page with a real
      row (see the bug below - this is exactly what caught it)

### Bugs found via this module and fixed
- [x] `GitDeploymentService` originally did `git checkout {branch}; git reset
      --hard {branch}` after fetching - `git fetch` only updates the
      remote-tracking ref (`origin/{branch}`), never the local branch pointer
      of the same name, so a second deploy silently redeployed the *original*
      clone-time commit forever. Fixed by resetting to `origin/{branch}`
      (or, for rollback, an explicit commit SHA) instead of the bare branch
      name. Only caught because the test suite deploys twice and asserts the
      commit actually changed - a single-deploy test would have passed anyway.
- [x] `DeploymentWebhookController::__invoke()` was type-hinted to return
      `Illuminate\Http\Response`, but `response()->json(...)` returns
      `JsonResponse` - a sibling class, not a subclass, so PHP threw a
      `TypeError` on every successful webhook call. Fixed by widening the
      return type to `Symfony\Component\HttpFoundation\Response`, the common
      ancestor both actually extend.
- [x] **A Module 3 bug, only now surfacing**: `WebsitesTable`'s "suspend"
      action had an `->icon()` closure type-hinted `fn (Website $record):
      string`, but it returned `Heroicon::OutlinedNoSymbol`/
      `Heroicon::OutlinedCheckCircle` - enum instances, not strings. Icon
      closures are evaluated lazily, only when Filament renders an actual
      table row, so this passed every previous test (none rendered the list
      page with a row present) and only threw when this module's browser
      verification loaded `/admin/websites` with a real website in it. Fixed
      the type hint; added `ListWebsitesPageTest` as a standing regression
      guard, and confirmed no other `->icon()`/`->color()` closures in the
      codebase share the mistake.

### Wrap-up
- [x] `php artisan test` green (83 passed, 1 skipped - Linux-only)
- [x] `vendor/bin/pint` clean
- [x] Manually verified in browser: created a website, configured a real
      local git repo as its `repository_url` through the actual edit form,
      confirmed the Deploy action appears and (via the same underlying
      Action, since this session's browser automation can't reliably drive
      Filament's confirmation modals - see CLAUDE.md) that triggering it runs
      a real `git clone` against that repo
- [x] `docs/Database.md`, `docs/Features.md`, `docs/Roadmap.md` updated

## Done — Module 6: Laravel Deployment

### Schema
- [x] `deployment_steps` migration + `App\Models\DeploymentStep`, `Deployment::steps()`
- [x] `App\Enums\DeploymentStepStatus` (pending/running/success/failed/skipped -
      `Skipped` reserved for a possible future opt-out UI, unused today)

### Backend
- [x] `App\Services\Deployments\LaravelDeploymentPipelineService` - real
      `composer install` + `artisan storage:link/config:cache/route:cache/
      view:cache/migrate --force/queue:restart`, one `DeploymentStep` row per
      step, halts on first failure
- [x] Wired into `GitDeploymentService::deploy()`: after a successful git
      checkout, Laravel-framework websites run the pipeline before the
      deployment is marked `Success`; Plain PHP/Static sites skip it entirely
- [x] Rollback gets the same pipeline treatment as a normal deploy (it's just
      "deploy this other commit" - that old commit's dependencies need
      reinstalling too), not a bare git reset

### Filament / UI
- [x] `DeploymentInfolist` shows a "Laravel deployment steps" section
      (name/status/output per step) when steps exist, hidden for non-Laravel
      deployments

### Tests (`tests/Feature/Deployments/LaravelDeploymentPipelineServiceTest.php`, 2 new)
- [x] All 7 steps run in order and succeed - against a real `composer install`
      (trivial `composer.json`, no dependencies, no network needed) and the
      real PHP interpreter running a fake `artisan` script
- [x] A failing step (simulated `migrate` failure) halts every subsequent step -
      `queue:restart` never runs, proven by asserting its `DeploymentStep` row
      doesn't exist

### Fallout in Module 5's own tests
- [x] `GitDeploymentServiceTest`'s fixture websites were `WebsiteFramework::
      Laravel` with a fixture repo that has no `composer.json`/`artisan` - once
      the pipeline started auto-running for Laravel sites, those tests failed
      (pipeline's `composer install` step has nothing to install against).
      Switched those tests to `WebsiteFramework::PlainPhp`, since they test git
      mechanics specifically, not the Laravel pipeline - which now has its own
      dedicated test suite instead.

### Wrap-up
- [x] `php artisan test` green (85 passed, 1 skipped - Linux-only)
- [x] `vendor/bin/pint` clean
- [x] `docs/Database.md`, `docs/Features.md`, `docs/Roadmap.md` updated

## Done — Module 7: File Manager

No new database tables - file entries are read live from disk, scoped to each
website's `document_root`. This is the first module that is entirely
filesystem-facing rather than DB/process-facing, and the highest path-traversal
risk surface in the app so far.

### Backend
- [x] `App\Services\FileManager\FileManagerService` - the only class that
      touches a website's files. Every relative path argument is validated
      *before* resolution (rejects `..` segments, absolute/drive-letter paths,
      null bytes) and the **resolved** `realpath()` is re-checked against the
      website's document root *after* resolution too - this second check is
      the real guarantee, since it also catches symlink escapes that a
      syntax-only check would miss
- [x] `App\DTOs\FileManager\FileEntryData` (readonly) - `isImage()`/
      `isEditableText()` helpers based on extension, used to decide which row
      actions (Edit) a file gets
- [x] `zip()`/`unzip()` - `unzip()` guards against both "zip slip" (an archive
      entry name containing `../` that would write outside the destination
      directory - every entry's target path is re-validated, exactly like
      every other path in this class) and decompression bombs (rejects an
      archive whose uncompressed size exceeds 512 MB, or whose
      uncompressed:compressed ratio exceeds 100:1, computed from `statIndex()`
      *before* extracting anything)
- [x] Actions: `UploadFileAction`, `CreateDirectoryAction`, `RenameFileAction`,
      `DeleteFileAction`, `WriteFileAction`, `ZipFilesAction`, `UnzipFileAction`
      - each a thin delegation to the service plus an `activity('file_manager')`
      audit-log entry (filesystem mutations don't flow through Eloquent's
      `LogsActivity`, so these are logged manually, same pattern as
      `BackupDatabaseAction` in Module 4)
- [x] `WebsitePolicy::manageFiles()` - same developer-scoped-to-own-sites
      pattern as `update`/`suspend`; new `manage website files` permission
      wired into `admin` (all sites) and `developer` (own sites) roles, not
      `viewer`

### Filament / UI
- [x] `App\Filament\Resources\Websites\Pages\ManageFiles` - a custom Filament
      resource page (`Filament\Resources\Pages\Page` + `InteractsWithRecord`),
      not a `Resource`/`Table`, since file entries are DTOs off disk, not an
      Eloquent query Filament's table component could bind to
- [x] Plain-Blade table + forms (not `<x-filament::breadcrumbs>` or a Filament
      `Table`) since navigation between folders is pure Livewire component
      state, not routed URLs - styled with Filament's own Blade components
      (`x-filament::section`/`button`/`input`) to stay visually consistent
- [x] Upload, create folder, rename (inline), delete (`wire:confirm` - a
      native browser `confirm()`, deliberately *not* a Filament
      `->requiresConfirmation()` modal, since those are unreliable to drive in
      this session's browser automation - see CLAUDE.md), edit (plain
      `<textarea>`, Monaco explicitly deferred), download, zip (per-item),
      unzip (per `.zip` file)
- [x] `WebsitesTable` gets a "Files" row action linking to the new page,
      gated by `manageFiles`

### Tests (`tests/Unit/Services/FileManager/`, `tests/Feature/Websites/ManageFilesPageTest.php`, 24 new)
- [x] `FileManagerServiceTest` - real temp-directory filesystem (not mocked):
      list/create/rename/delete/write/read/zip+unzip round-trip, **and** the
      security boundary: `..` rejection, absolute-path rejection, null-byte
      rejection, a resolved-path-escapes-root case using a real sibling
      directory on disk (not just a string check), zip-slip (a real crafted
      malicious zip with a `../escaped.txt` entry, asserting the file never
      lands outside the document root), and the decompression-bomb ratio guard
      (a real highly-compressible payload, asserting rejection)
- [x] `ManageFilesPageTest` - the real Livewire page via `Livewire::test()`:
      renders with a real row, an authorization-denial test (`viewer` role
      gets a 403 from `mount()`), and every mutating action end-to-end
      (create folder, upload, edit+save, rename, delete, navigate in/out of
      subdirectories, and a path-traversal attempt from the component itself
      recovering gracefully instead of throwing to the browser)
- [x] `WebsitePolicyTest` - `manageFiles` scoping (developer own-site-only,
      viewer denied)

### Bugs found via this module and fixed
- [x] A public Livewire property typed `Collection<FileEntryData>` failed at
      render time with "Property type not supported in Livewire" - Livewire's
      synth system only knows how to (de)hydrate specific types (arrays,
      Eloquent models/collections, primitives, registered synths), and a
      plain `Collection` of custom DTOs isn't one of them. Fixed by making
      `entries()` a `#[Computed]` method instead of a public property -
      derived from other component state (`currentDirectory` + the record),
      recomputed fresh each render, never serialized as component state at
      all. `unset($this->entries)` clears the in-request memo after a
      mutating action.
- [x] `UploadFileAction`/`FileManagerService::upload()` originally called
      `$file->move($dir, $filename)` (Symfony's rename-or-copy). This passed
      against a plain `UploadedFile::fake()` in a unit test but failed with an
      empty-message `FileException` when driven through a real
      `Livewire::test()` file upload - Livewire's `TemporaryUploadedFile` has
      its own temp-disk lifecycle that doesn't always tolerate a bare
      `move()`. Fixed by reading the temp file's contents and writing them
      out (`File::put($target, File::get($file->getRealPath()))`) instead,
      which works uniformly for both a real HTTP upload and a Livewire
      temporary upload.
- [x] A Blade component prop written as `heading="Upload &amp; create"`
      rendered the literal text `Upload &amp; create` in the browser instead
      of `Upload & create` - Filament's section heading is output through
      `{{ }}`, which escapes it again, double-encoding the entity. Only
      caught by an actual browser render (`get_page_text`), not by any
      Livewire feature test, since none of them asserted on that exact
      string. Fixed by writing a literal `&` in the Blade source.
- [x] A typed class constant (`private const int MAX_UNCOMPRESSED_BYTES = ...`)
      is a PHP 8.3+ syntax feature and doesn't parse on this machine's PHP
      8.2.31 - caught immediately by `php -l`, fixed by dropping the type.

### Wrap-up
- [x] `php artisan test` green (111 passed, 1 skipped - Linux-only)
- [x] `vendor/bin/pint` clean
- [x] Manually verified in browser: logged in, opened a real website's File
      Manager page, navigated into a subdirectory, created a new folder
      (confirmed on disk), opened and edited a real text file (confirmed the
      new content was actually written to disk), confirmed the audit log
      recorded both actions with the right properties, confirmed the
      Websites list page's new "Files" row action links through correctly -
      no console errors
- [x] `docs/Database.md`, `docs/Features.md`, `docs/Roadmap.md`, `docs/Security.md`
      updated

## Done — Module 8: Terminal

Scoped as one-shot command execution per line, not a true PTY/WebSocket bridge -
see CLAUDE.md for the full reasoning (no long-lived process manager exists outside
PHP-FPM/`artisan serve`'s request lifecycle in this environment). `cd` is
special-cased so directory navigation still feels continuous across commands.

### Schema
- [x] `terminal_sessions` (server_id, user_id, label, current_directory,
      closed_at - not deleted on close, just marked, for history)
- [x] `terminal_commands` (one row per submitted line, executed or blocked -
      this table **is** the audit log for this module)
- [x] `App\Enums\TerminalCommandStatus` (Executed/Blocked)

### Backend
- [x] `App\Services\Terminal\DangerousCommandGuard` - a fixed, auditable regex
      blocklist (`rm -rf /`, `mkfs`, a `dd ... of=/dev/...`, the fork bomb,
      `DROP DATABASE`/`TABLE`, `TRUNCATE`, `shutdown`/`reboot`, Windows
      equivalents) - a confirm-before-you-nuke-it safety net, not an allowlist
      security boundary (Terminal is arbitrary shell access by design)
- [x] `App\Services\Terminal\TerminalCommandService::execute()` - real
      `Symfony\Process::fromShellCommandline()` per command (shell semantics
      like `&&`/pipes are expected here), `cd` intercepted to update
      `current_directory` without spawning a process, 30s timeout per command
- [x] `App\Policies\ServerPolicy::useTerminal()` - admin/super-admin only, not
      scoped to "sites I created" like Website/Database (a server shell
      reaches everything on that server); new `use terminal` permission
- [x] `OpenTerminalSessionAction`/`CloseTerminalSessionAction` - session
      lifecycle events get one `activity('terminal')` log entry each;
      individual commands don't duplicate this since `terminal_commands`
      already records them

### Filament / UI
- [x] `App\Filament\Pages\Terminal` (plain auto-discovered Filament page, not
      a resource) - multiple tabs (`TerminalSession` rows), a "type yes to
      confirm" flow for guard-blocked commands (server-side state, not a
      Filament `->requiresConfirmation()` modal - consistent with those being
      unreliable to drive in this session's browser automation)
- [x] Real `@xterm/xterm` + `@xterm/addon-fit` (npm), a small hand-written
      line-buffered input bridge (`resources/js/terminal.js`) - not a raw
      PTY pass-through, Enter submits the buffered line via `$wire.call()`
- [x] Monaco/full raw-keystroke PTY explicitly out of scope for this module,
      matching Module 7's precedent of choosing the honestly-buildable option

### Tests (`tests/Unit/Services/Terminal/`, `tests/Feature/Terminal/`, 21 new)
- [x] `TerminalCommandServiceTest` - real spawned processes (`echo`/`exit`,
      not mocked), real `cd` into a real temp subdirectory and back out,
      the dangerous-command block + confirmed-bypass flow (using
      `DROP DATABASE production` specifically because "DROP" isn't a real
      executable anywhere, so even the "confirmed, actually runs" test can't
      damage the test machine - never a genuine `rm -rf`/fork-bomb pattern)
- [x] `ServerPolicyTest` - admin/super-admin only, developer/viewer denied
- [x] `TerminalPageTest` - the real Livewire page: open/close tabs, run a
      real command end to end, the confirm-with-"yes" flow, a developer
      denied at `mount()` (403), and one user cannot call `runCommand`
      against another user's session (ownership check, not just role)

### Bugs found via this module and fixed
- [x] `Terminal::$openSessions` (a public property returning an array of
      `TerminalSession` Eloquent models) hit the same "Property type not
      supported in Livewire" wall as Module 7's `Collection<FileEntryData>` -
      fixed the same way, with `#[Computed]`.
- [x] Opening a tab in the browser mounted **two** xterm.js instances inside
      one terminal pane - Alpine's `x-init` on a `wire:ignore`'d element fired
      twice for the same DOM node (Livewire's morph hook and Alpine's own
      observer both processing the freshly-inserted node). A dataset-flag
      guard in the Blade `x-init` expression did **not** fix it - both calls
      happened before either flag-set was visible to the other. Fixed with an
      idempotency guard inside the plain JS function itself
      (`if (el._mtpTerminal) return el._mtpTerminal;`), immune to how many
      times the Alpine wrapper invokes it.

### Wrap-up
- [x] `php artisan test` green (128 passed, 1 skipped - Linux-only)
- [x] `vendor/bin/pint` clean
- [x] Manually verified in browser: logged in, opened the Terminal page, saw
      the real xterm.js instance mount (confirmed via DOM inspection, one
      instance after the fix), and confirmed the full round trip end-to-end
      by invoking the Livewire component's `runCommand` directly - a real
      `echo` executed server-side, its output returned, and a matching
      `terminal_commands` row persisted with the correct output/exit
      code/status. (Simulated keystrokes through the browser automation
      layer didn't reach xterm.js's input capture - consistent with this
      session's documented non-compositing browser pane limitation, not an
      application bug; the Livewire-level round trip is what actually proves
      the feature works.)
- [x] `docs/Database.md`, `docs/Features.md`, `docs/Roadmap.md`, `docs/Security.md`
      updated

## Done — Module 9: Cloudflare

The first module to deliberately break the "real infrastructure over mocks"
testing pattern used since Module 4 - Cloudflare is a third-party SaaS needing
real account credentials this dev environment doesn't have. See CLAUDE.md for
the full reasoning; tests use `Http::fake()` against Cloudflare's real,
documented API v4 shapes instead. **A manual smoke test against a real
Cloudflare zone is recommended before production use** - a disclosed gap.

### Schema
- [x] `cloudflare_zones` (website_id unique, zone_id, api_token encrypted,
      ssl_mode, last_synced_at) - one zone per website, each with its own token
- [x] `cloudflare_tunnels` (server_id, cloudflare_tunnel_id, name, status) -
      account-scoped in Cloudflare's model, so server-scoped here, not
      website-scoped
- [x] `App\Enums\CloudflareSslMode` (off/flexible/full/strict - matches
      Cloudflare's real setting values exactly), `App\Enums\CloudflareTunnelStatus`
      (active/inactive/error - never set to Active by this module, see below)
- DNS records are **not** persisted - fetched live from Cloudflare on every
  page load, same principle as Module 7's live filesystem reads

### Backend
- [x] `App\Services\Cloudflare\CloudflareApiClient` - thin wrapper over
      Cloudflare's real REST API v4 (`Http::withToken()`, the
      `{success, errors, result}` envelope), covering DNS list/create/delete,
      SSL mode update, cache purge, tunnel list/create/delete
- [x] `App\Services\Cloudflare\CloudflareZoneService` - per-website zone
      orchestration (connect/disconnect/DNS/SSL/purge), each website using its
      own stored token
- [x] `App\Services\Cloudflare\CloudflareTunnelService` - account-scoped
      tunnel object orchestration only; **does not install or run the real
      `cloudflared` connector daemon** - a tunnel created here carries no
      traffic until that separate, unbuilt step happens
- [x] 8 Actions (Connect/DisconnectCloudflareZone, Create/DeleteDnsRecord,
      UpdateSslMode, PurgeCache, Create/DestroyTunnel), each logging one
      `activity('cloudflare')` entry
- [x] Zone/DNS/SSL/cache actions reuse the existing `WebsitePolicy::update()`
      ability (no new permission - same trust level as toggling SSL or
      deploying); `ServerPolicy::manageTunnels()` (new `manage cloudflare
      tunnels` permission, admin/super-admin only) for tunnels specifically

### Filament / UI
- [x] `App\Filament\Resources\Websites\Pages\ManageCloudflare` - per-website
      custom page: connect form when no zone exists, then zone info/SSL
      mode/purge/disconnect plus a live DNS records table with add/delete
- [x] `App\Filament\Pages\CloudflareTunnels` - a separate, admin-only page
      (not per-website) for account-level tunnel create/destroy, with UI copy
      that's upfront about the connector-daemon gap
- [x] `WebsitesTable` gets a "Cloudflare" row action linking to the new page

### Tests (`tests/Unit/Services/Cloudflare/`, `tests/Feature/Cloudflare/`, 20 new)
- [x] `CloudflareApiClientTest` - `Http::fake()` against Cloudflare's real
      response shapes: list/create/delete DNS records, update SSL mode, purge
      cache, and a failed-request case asserting the real error message is
      surfaced
- [x] `CloudflareTunnelServiceTest` - create persists a local row only on
      success, destroy removes it, a failed API call leaves no orphaned row
- [x] `CloudflareActionsTest` - every Action end-to-end plus policy scoping
      (developer can connect a zone on their own site, not another's; only
      admin can manage tunnels)
- [x] `ManageCloudflarePageTest`, `CloudflareTunnelsPageTest` - the real
      Livewire pages: connect flow, authorization denial (viewer/developer
      forbidden), create/destroy a tunnel end-to-end

### Wrap-up
- [x] `php artisan test` green (148 passed, 1 skipped - Linux-only)
- [x] `vendor/bin/pint` clean
- [x] Manually verified in browser: connected a zone through the real UI
      (zone ID + a placeholder token - safe, since `connect()` only writes to
      the local DB, no API call happens until DNS records are listed), saw
      the zone management UI render with an honest "no records found" state
      once the real (but fake-token) Cloudflare API call correctly failed,
      and confirmed the Cloudflare Tunnels admin page renders with no
      console errors
- [x] `docs/Database.md`, `docs/Features.md`, `docs/Roadmap.md`, `docs/Security.md`
      updated

## Done — Module 10: SSL

Hand-written minimal ACME v2 (RFC 8555) client since `acmephp/core` is
dependency-incompatible with Laravel 12's Guzzle 7 stack. Cannot be verified
end-to-end in this dev environment (no public domain for Let's Encrypt to
validate against) - see CLAUDE.md.

- [x] `ssl_certificates` schema + `App\Enums\{CertificateType,CertificateStatus}`
- [x] `CertificateParserService` (real openssl_x509_parse/check_private_key,
      fully tested against genuine self-signed certs), `CertificateStorageService`
      (writes active cert/key PEM to disk for nginx)
- [x] `AcmeClient` - JWS signing (RS256), nonce handling, order/authorization/
      challenge/finalize/download; JWS signature verified against the real
      account public key in tests, not just asserted present
- [x] `LetsEncryptIssuanceService` - full orchestration; http-01 writes the
      challenge file into the website's own document root, dns-01 reuses
      Module 9's `CloudflareZoneService` for wildcard domains
- [x] Actions: Upload/IssueLetsEncrypt/Renew/RevokeCertificate, each logging
      `activity('ssl')`; `SslCertificate::syncWebsiteSslStatus()` keeps
      `Website::ssl_status` truthful after every mutation
- [x] `app:renew-ssl-certificates` (scheduled daily) renews certs within
      `mtp.ssl_renewal_threshold_days` of expiry
- [x] `NginxConfigGeneratorService` emits a real `listen 443 ssl` block +
      http→https redirect once SSL is Active
- [x] `ManageSsl` Filament page (issue/upload/renew/revoke/history), reusing
      `WebsitePolicy::update()` - no new permission
- [x] Real Windows/AMPPS gotcha found and fixed: this PHP build has no
      working default `openssl.cnf`, so every `openssl_pkey_new()`/
      `openssl_csr_new()` call needs an explicit `config` path
      (`config('mtp.openssl_config_path')`) - not needed on a real Linux server
- [x] `php artisan test` green (173 passed, 1 skipped), `vendor/bin/pint` clean
- [x] `docs/Database.md`, `docs/Features.md`, `docs/Roadmap.md`, `docs/Security.md` updated

## Up Next
- [ ] Module 11 — Cron Manager (see docs/Roadmap.md)
- [ ] ...through Module 20, one at a time, per docs/Roadmap.md
