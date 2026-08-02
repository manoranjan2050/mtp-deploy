# Database — MTP Deploy

Engine: MariaDB (MySQL protocol). Charset `utf8mb4`, collation `utf8mb4_unicode_ci`.
All tables use unsigned bigint auto-increment primary keys and `timestamps()` unless
noted. Soft deletes (`deleted_at`) are used on user-facing aggregates that support
recovery (sites, databases, backups) but not on pure log/audit tables.

This document is added to incrementally as each module is built. Only Module 1's
tables are final; later modules are sketched for forward-compatibility and will be
refined when their module starts.

## Module 1 — Authentication ✅ (as built)

### `users`
| Column | Type | Notes |
|---|---|---|
| id | bigint pk | |
| name | string | |
| email | string unique | |
| email_verified_at | timestamp nullable | |
| password | string | hashed |
| app_authentication_secret | text nullable | encrypted cast; Filament's native MFA column name (not `two_factor_secret` — see docs/Architecture.md on why Filament's built-in MFA is used instead of a hand-rolled package) |
| app_authentication_recovery_codes | text nullable | encrypted array cast |
| is_active | boolean default true | suspend a user without deleting |
| last_login_at | timestamp nullable | set by `RecordLastLogin` listener on `Illuminate\Auth\Events\Login` |
| last_login_ip | string(45) nullable | |
| remember_token | string nullable | |
| timestamps, soft deletes | | |

### `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`
Standard `spatie/laravel-permission` tables (teams feature off — single-tenant panel
for now). Seeded roles: `super-admin` (no direct permissions, bypassed entirely via
`Gate::before`), `admin`, `developer`, `viewer`. Ten permissions seeded, covering
users/roles/activity-log (see `database/seeders/PermissionSeeder.php`).

### `sessions`
Laravel's built-in `sessions` table (database session driver) doubles as the source
for the "active sessions" UI (`App\Filament\Pages\Profile\Sessions`). Wrapped by a
read-only `App\Models\Session` Eloquent model since Filament's table component
requires an Eloquent query, not a raw `DB::table()` query builder. Displayed fields:
`ip_address`, `user_agent`, `last_activity`, with a "this device" flag and a per-row
"log out" action plus a "log out other devices" header action.

### `personal_access_tokens`
Laravel Sanctum's built-in table, surfaced via `App\Filament\Pages\Profile\ApiTokens`.
Abilities are drawn from `App\Enums\ApiTokenAbility` (`profile:read`,
`sessions:write`, `*`) — grows as later modules add their own API surface.

### `activity_log`
`spatie/laravel-activitylog`'s built-in table, surfaced read-only via
`App\Filament\Resources\ActivityLogs\ActivityLogResource` (no create/edit/delete
pages registered - see docs/Security.md on audit logs being append-only).

## Module 2 — Dashboard ✅ (as built)

### `system_metric_snapshots`
| Column | Type | Notes |
|---|---|---|
| id | bigint pk | |
| is_supported | boolean default true | false when captured on a non-Linux host - `cpu_usage_percent` etc. are null in that case, never fabricated |
| cpu_usage_percent | float nullable | derived from two `/proc/stat` reads 100ms apart |
| memory_used_bytes | bigint unsigned nullable | `/proc/meminfo`: `MemTotal - MemAvailable` |
| memory_total_bytes | bigint unsigned nullable | |
| disk_used_bytes | bigint unsigned nullable | root filesystem, via `disk_total_space()`/`disk_free_space()` |
| disk_total_bytes | bigint unsigned nullable | |
| load_1min / load_5min / load_15min | float nullable | `sys_getloadavg()` |
| network_rx_bytes / network_tx_bytes | bigint unsigned nullable | summed across non-loopback interfaces, `/proc/net/dev` |
| recorded_at | timestamp | when the snapshot was taken (no `updated_at` - append-only) |

Populated every minute by `php artisan app:capture-system-metrics`
(`App\Console\Commands\CaptureSystemMetrics`), scheduled in `routes/console.php`.
`App\Filament\Widgets\MetricsTrendChart` reads the last 60 rows for its Chart.js
line chart; no other module reads this table (no model relationships needed beyond
the plain `App\Models\SystemMetricSnapshot`).

## Forward-Looking Schema (sketched, subject to change per-module)

### `servers` (Module 18, present from Module 1 as `is_local` singleton row)
id, name, hostname, ssh_host, ssh_port, ssh_user, ssh_private_key (encrypted),
is_local (bool), status (enum: pending/connected/unreachable), os, php_versions
(json), created_by, timestamps.

### `websites` (Module 3)
id, server_id (fk servers), name, domain, aliases (json), document_root, php_version,
framework (enum: laravel/plain-php/static), status (enum: active/suspended),
ssl_status (enum: none/pending/active/expired), created_by, timestamps, soft deletes.

### `databases` / `database_users` (Module 4)
`databases`: id, server_id, website_id nullable, name, charset, collation, status,
timestamps, soft deletes.
`database_users`: id, server_id, username, password (encrypted), host, timestamps.
`database_user_database` (pivot): database_user_id, database_id, privileges (json).

### `deployments` (Module 5/6)
id, website_id, provider (enum: github/gitlab/bitbucket/manual), repository, branch,
commit_sha, status (enum: pending/running/success/failed/rolled_back), triggered_by
(enum: manual/webhook), started_at, finished_at, log (longtext), timestamps.

### `deployment_steps` (Module 6)
id, deployment_id, name (e.g. `composer install`, `artisan migrate`), status, output,
started_at, finished_at, `order` column.

### `ssl_certificates` (Module 10)
id, website_id, type (enum: lets_encrypt/custom), domains (json), issued_at,
expires_at, status (enum: active/expiring/expired/revoked), auto_renew (bool).

### `cron_jobs` (Module 11)
id, server_id, website_id nullable, label, command, schedule (cron expression),
is_enabled, last_run_at, last_exit_code, timestamps.

### `queue_workers` (Module 12)
id, website_id, connection, queue, processes, status (enum: running/stopped/failed),
supervisor_program_name, timestamps.

### `backups` (Module 13)
id, backupable_type/backupable_id (morph: website|database), disk, path, size_bytes,
type (enum: manual/scheduled), status, created_by, timestamps, soft deletes.

### `notification_channels` (Module 16)
id, user_id, channel (enum: email/telegram/discord/slack), config (encrypted json),
is_enabled, timestamps.

### `docker_containers` / `docker_images` (Module 19)
Sketched only — schema finalized in Module 19.

## Conventions
- Every `*_id` foreign key has an explicit `->constrained()->cascadeOnDelete()` or
  `->nullOnDelete()`, chosen deliberately per relationship (never left implicit).
- Secrets (SSH keys, DB passwords, notification channel tokens, 2FA secrets) are
  always stored via Laravel's `encrypted` Eloquent cast — never plaintext, never
  application-level custom encryption.
- Every enum-like column is backed by a real PHP Enum class in `app/Enums`, cast via
  `casts()` on the model — no magic strings in queries.
