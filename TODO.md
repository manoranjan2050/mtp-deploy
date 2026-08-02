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

## Up Next
- [ ] Module 5 — Deployment (see docs/Roadmap.md)
- [ ] ...through Module 20, one at a time, per docs/Roadmap.md
