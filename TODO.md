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
      this way - Sessions page needed an Eloquent model, not a raw query builder)
- [ ] `docs/Database.md` Module 1 section - reviewed, matches implementation
      (column names updated to Filament's MFA convention)
- [x] `docs/Features.md` Module 1 checklist ticked
- [x] `docs/Roadmap.md` Module 1 status → ✅

## Up Next
- [ ] Module 2 — Dashboard (see docs/Roadmap.md)
- [ ] Module 3 — Website Manager
- [ ] ...through Module 20, one at a time, per docs/Roadmap.md
