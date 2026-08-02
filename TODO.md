# TODO — MTP Deploy

Live, granular checklist. High-level module order lives in
[docs/Roadmap.md](docs/Roadmap.md); full feature list lives in
[docs/Features.md](docs/Features.md). This file tracks exactly what's in flight
right now so a new session (human or AI) can pick up without re-deriving state.

## Done
- [x] Repo scaffolded: `composer create-project laravel/laravel MTPDeploy "12.*"`
- [x] Local MariaDB-compatible MySQL 8.0.46 (AMPPS) started, `mtpdeploy` database created
- [x] `.env` configured for MySQL (`root`, no password, `mtpdeploy` db) — Redis not
      yet installed locally, cache/queue/session run on `database` driver for now
- [x] All 10 architecture docs written in `docs/`
- [x] README.md, TODO.md (this file), CLAUDE.md written

## In Progress — Module 1: Authentication

### Packages
- [ ] `livewire/livewire`
- [ ] `filament/filament` (v4)
- [ ] `spatie/laravel-permission`
- [ ] `spatie/laravel-activitylog`
- [ ] `pragmarx/google2fa-laravel` (or Filament's built-in 2FA if v4 ships one —
      confirm during install)
- [ ] `laravel/sanctum` (ships with Laravel 12 skeleton — confirm, don't reinstall)

### Schema
- [ ] Extend `users` migration: `two_factor_secret`, `two_factor_recovery_codes`,
      `two_factor_confirmed_at`, `is_active`, `last_login_at`, `last_login_ip`
- [ ] Publish + run spatie/permission migrations
- [ ] Publish + run spatie/activitylog migrations
- [ ] `RoleSeeder` (super-admin, admin, developer, viewer)
- [ ] `PermissionSeeder`
- [ ] `DatabaseSeeder` creates a default super-admin only in `local`/`testing` env

### Backend
- [ ] `User` model: fillable, casts (encrypted 2FA fields), `HasRoles`,
      `LogsActivity`, relations
- [ ] `Actions/Auth/*` per docs/FolderStructure.md
- [ ] `Policies/UserPolicy`, `Policies/RolePolicy`
- [ ] Explicit event/listener registration in `AppServiceProvider::boot()`
      (Laravel 12 has no auto-discovery — see docs/CodingStandards.md)

### Filament / UI
- [ ] Install Filament panel (`admin`)
- [ ] Custom Login page (rate-limited, 2FA challenge step)
- [ ] Custom Register page (bootstrap-only: hidden once ≥1 user exists)
- [ ] Forgot Password / Reset Password pages
- [ ] Profile page: name/email/password/avatar/timezone
- [ ] Profile → 2FA enroll/confirm/disable UI (QR + recovery codes)
- [ ] Profile → Sessions manager (list + revoke one + revoke others)
- [ ] Profile → API Tokens manager (create with ability scopes, list, revoke)
- [ ] `UserResource` (Filament) with role assignment
- [ ] `RoleResource` / permission matrix UI
- [ ] Activity Log viewer (searchable, filterable by causer/subject/date)
- [ ] Dark mode toggle (Filament built-in, verify it's wired up)

### Tests
- [ ] Login (happy path, wrong password, rate-limit, inactive user blocked)
- [ ] Register (only while zero users, blocked after)
- [ ] Forgot/reset password (no enumeration, sessions invalidated after reset)
- [ ] 2FA enroll/confirm/challenge/disable, recovery code usage
- [ ] Sessions: revoke one, revoke others, denies revoking another user's session
- [ ] API tokens: create/list/revoke, ability scoping enforced
- [ ] Roles/permissions: `developer` denied admin-only actions (policy "denies" test)
- [ ] Activity log entries created for register/login/role-change/token-create

### Wrap-up
- [ ] `php artisan test` green
- [ ] `vendor/bin/pint` clean
- [ ] `docs/Database.md` Module 1 section reviewed/finalized
- [ ] `docs/Features.md` Module 1 checklist ticked
- [ ] `docs/Roadmap.md` Module 1 status → ✅
- [ ] Commit, then move to Module 2 (Dashboard)

## Up Next
- [ ] Module 2 — Dashboard (see docs/Roadmap.md)
- [ ] Module 3 — Website Manager
- [ ] ...through Module 20, one at a time, per docs/Roadmap.md
