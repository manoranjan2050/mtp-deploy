# Database — MTP Deploy

Engine: MariaDB (MySQL protocol). Charset `utf8mb4`, collation `utf8mb4_unicode_ci`.
All tables use unsigned bigint auto-increment primary keys and `timestamps()` unless
noted. Soft deletes (`deleted_at`) are used on user-facing aggregates that support
recovery (sites, databases, backups) but not on pure log/audit tables.

This document is added to incrementally as each module is built. Only Module 1's
tables are final; later modules are sketched for forward-compatibility and will be
refined when their module starts.

## Module 1 — Authentication

### `users`
| Column | Type | Notes |
|---|---|---|
| id | bigint pk | |
| name | string | |
| email | string unique | |
| email_verified_at | timestamp nullable | |
| password | string | hashed |
| two_factor_secret | text nullable | encrypted cast |
| two_factor_recovery_codes | text nullable | encrypted cast |
| two_factor_confirmed_at | timestamp nullable | |
| is_active | boolean default true | suspend a user without deleting |
| last_login_at | timestamp nullable | |
| last_login_ip | string nullable | |
| remember_token | string nullable | |
| timestamps, soft deletes | | |

### `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`
Standard `spatie/laravel-permission` tables (teams feature off — single-tenant panel
for now). Seeded roles: `super-admin`, `admin`, `developer`, `viewer`.

### `sessions`
Laravel's built-in `sessions` table (database session driver) doubles as the source
for the "active sessions" UI in the user profile (Module 1 requirement). Displayed
fields: `ip_address`, `user_agent`, `last_activity`, with a "this device" flag and a
per-row "log out" action (`DELETE` the row) and a "log out other devices" bulk action.

### `personal_access_tokens`
Laravel Sanctum's built-in table, surfaced in the profile as "API Tokens" with
scoped abilities and an expiry.

### `activity_log`
`spatie/laravel-activitylog`'s built-in table. Every Action class logs
`causer`/`subject`/`description`/`properties` (before/after diff where relevant).

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
