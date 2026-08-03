# Database — MTP Deploy

Engine: MariaDB (MySQL protocol). Charset `utf8mb4`, collation `utf8mb4_unicode_ci`.
All tables use unsigned bigint auto-increment primary keys and `timestamps()` unless
noted. Soft deletes (`deleted_at`) are used on user-facing aggregates that support
recovery (sites, databases, backups) but not on pure log/audit tables.

This document is added to incrementally as each module is built. Modules 1–6's
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

## Module 3 — Website Manager ✅ (as built)

### `servers`
| Column | Type | Notes |
|---|---|---|
| id | bigint pk | |
| name | string | |
| hostname | string nullable | |
| ssh_host | string nullable | |
| ssh_port | unsigned smallint default 22 | |
| ssh_user | string nullable | |
| ssh_private_key | text nullable | encrypted cast; unused until Module 18 |
| is_local | boolean default false | the one seeded row (`ServerSeeder`) has this true - every module through 17 operates against it |
| status | string, cast to `App\Enums\ServerStatus` | pending/connected/unreachable |
| os | string nullable | `PHP_OS_FAMILY` for the local row |
| php_versions | json nullable | selectable PHP versions for websites on this server; falls back to `['8.2','8.3','8.4']` when empty (`Server::availablePhpVersions()`) |
| created_by | fk users, nullOnDelete | |
| timestamps | | |

### `websites`
| Column | Type | Notes |
|---|---|---|
| id | bigint pk | |
| server_id | fk servers, cascadeOnDelete | |
| name | string | |
| domain | string unique | immutable after create in the UI - changing it needs delete + recreate (Module 3 doesn't support in-place domain moves) |
| aliases | json nullable | extra `server_name` entries in the generated vhost |
| document_root | string | auto-derived from `config('mtp.sites_root')."/{$domain}"` on create, never user-entered |
| php_version | string | e.g. `"8.3"` - drives the vhost's `fastcgi_pass` socket path |
| framework | string, cast to `App\Enums\WebsiteFramework` default `laravel` | laravel/plain-php/static; determines the `/public` suffix on the served path |
| status | string, cast to `App\Enums\WebsiteStatus` default `active` | active/suspended - suspending swaps the *actual* vhost content to a 503 block, not just a cosmetic flag |
| ssl_status | string, cast to `App\Enums\SslStatus` default `none` | none/pending/active/expired - Module 3 only ever sets none/pending (real issuance is Module 10) |
| created_by | fk users, nullOnDelete | drives `WebsitePolicy`'s developer-scoping ("assigned sites" simplified to "created_by = self" - see `database/seeders/RoleSeeder.php`) |
| timestamps, soft deletes | | |

**Model default gotcha (recurring across this project):** `Website`'s
`framework`/`status`/`ssl_status` all have DB-level defaults but Eloquent doesn't
hydrate those onto a freshly-created, unrefreshed instance - a `match()` against the
enum-cast attribute throws `UnhandledMatchError` until the row is fetched fresh. Same
root cause as `User::is_active` in Module 2. Fixed with an in-memory
`protected $attributes = [...]` default on the model matching the migration's own
defaults - do this for every new model with a DB column default.

### `system_command` activity log entries
Not a new table - `App\Services\System\SystemCommandService` (the only class allowed
to construct a `Symfony\Component\Process\Process`, per docs/Architecture.md) logs to
the existing `activity_log` table under log name `system-command`, once before
running a whitelisted operation and once after with the exit code and captured
output/error (truncated to 4000 chars).

## Module 4 — Database Manager ✅ (as built)

### `databases`
| Column | Type | Notes |
|---|---|---|
| id | bigint pk | |
| server_id | fk servers, cascadeOnDelete | |
| website_id | fk websites nullable, nullOnDelete | optional link, drives `DatabasePolicy`'s developer-scoping (same "created_by = self" pattern as `WebsitePolicy`, via the linked website's `created_by`) |
| name | string unique | validated against `^[a-zA-Z0-9_]{1,64}$` before ever reaching SQL (see `DatabaseManagerService`) |
| charset | string default `utf8mb4` | |
| collation | string default `utf8mb4_unicode_ci` | |
| status | string, cast to `App\Enums\DatabaseStatus` default `active` | active/restoring |
| created_by | fk users, nullOnDelete | |
| timestamps, soft deletes | | |

**Unlike `Website`, this row is only ever created if the real `CREATE DATABASE`
statement succeeds first** (`CreateDatabaseAction`) - a "database" record with no
actual database behind it would be actively misleading, whereas a website can
meaningfully exist as intent before nginx catches up.

### `database_users`
| Column | Type | Notes |
|---|---|---|
| id | bigint pk | |
| server_id | fk servers, cascadeOnDelete | |
| username | string unique | validated the same way as database names |
| password | text, encrypted cast | the real MySQL account's password, needed later for `mysqldump`/`mysql` option-file generation |
| host | string default `%` | validated against `^[a-zA-Z0-9.%_-]{1,60}$` |
| created_by | fk users, nullOnDelete | |
| timestamps, soft deletes | | |

### `database_user_database` (pivot)
| Column | Type | Notes |
|---|---|---|
| id | bigint pk | |
| database_user_id | fk database_users, cascadeOnDelete | |
| database_id | fk databases, cascadeOnDelete | |
| privileges | json | e.g. `["SELECT","INSERT"]`; requires a dedicated `App\Models\DatabaseUserDatabase` Pivot model with its own `casts()` - Eloquent does **not** auto-cast pivot attributes on the default anonymous pivot, and `sync()`/`attach()` with a plain PHP array fails with "Array to string conversion" without one |
| timestamps | | unique on `(database_user_id, database_id)` |

### Two PDO/MySQL gotchas found building `DatabaseManagerService`
1. A `?` placeholder immediately after `@` (as in `` `user`@? ``) is not bound by
   the MySQL PDO driver - it's read as a user-defined-variable reference, not "at
   symbol then a parameter." The `host` part of `user@host` is validated against a
   strict allowlist pattern and safely interpolated instead of bound.
2. `CREATE USER ... IDENTIFIED BY ?` does not support a bound parameter at all -
   it isn't a preparable DML statement, and MySQL returns a syntax error with the
   placeholder left as a literal `?`. The password is escaped with
   `PDO::quote()` and interpolated directly (still never raw/unescaped user
   input).

Confirmed against this dev machine's real MySQL 8.0.46 - see
`tests/Unit/Services/Databases/DatabaseManagerServiceTest.php`.

### `mysql_admin` connection
A separate, more-privileged database connection (`config/database.php`) used only
by `DatabaseManagerService`/`DatabaseBackupService` - the app's own `mysql`
connection only has grants on its own `mtpdeploy` database. Configured via
`DB_ADMIN_HOST`/`DB_ADMIN_PORT`/`DB_ADMIN_USERNAME`/`DB_ADMIN_PASSWORD`; on this dev
machine, `root@127.0.0.1` with no password. Tests use the same real connection
against uniquely-named throwaway databases/users, cleaned up in `tearDown()` - see
CLAUDE.md.

## Module 5 — Deployment ✅ (as built)

### `websites` additions
| Column | Type | Notes |
|---|---|---|
| repository_url | string nullable | plain git URL (HTTPS or SSH) - no OAuth/provider-specific storage |
| git_branch | string default `main` | |
| webhook_token | string(64) unique nullable | random 40 chars, auto-generated in `Website::booted()`'s `creating` hook; doubles as the HMAC shared secret for the webhook |
| auto_deploy | boolean default false | gates whether the webhook actually triggers a deployment |

### `deployments`
| Column | Type | Notes |
|---|---|---|
| id | bigint pk | |
| website_id | fk websites, cascadeOnDelete | |
| provider | string, cast to `App\Enums\DeploymentProvider` default `manual` | github/gitlab/bitbucket/manual - currently informational only, since Module 5 clones via plain git URL regardless of provider |
| branch | string | |
| commit_sha | string(40) nullable | null until a deploy actually succeeds |
| status | string, cast to `App\Enums\DeploymentStatus` default `pending` | pending/running/success/failed/rolled_back |
| triggered_by | string, cast to `App\Enums\DeploymentTrigger` default `manual` | manual/webhook |
| triggered_by_user_id | fk users nullable, nullOnDelete | null for webhook-triggered deployments |
| started_at / finished_at | timestamp nullable | |
| log | longtext nullable | every git command run + its output, appended as the deployment progresses (`Deployment::appendLog()`) |
| timestamps | | no soft deletes - deployment history is append-only, same as `activity_log` |

### `GitDeploymentService` - two real bugs found running against actual git
1. `git fetch` only updates the **remote-tracking** ref (`origin/<branch>`) - it
   never moves the local branch pointer of the same name. Resetting to the bare
   branch name after fetching silently redeploys whatever was checked out at
   clone time, forever. A plain deploy resets to `origin/{branch}`; rollback
   passes an explicit commit SHA instead, used as-is.
2. A redundant `git checkout <ref>` before `git reset --hard <ref>` was
   unnecessary - `git reset --hard` alone moves both HEAD and the working tree
   to the target, checkout or not.

Confirmed against a real local bare-repository fixture (not GitHub) - see
`tests/Feature/Deployments/GitDeploymentServiceTest.php`, which runs an actual
clone → deploy → second-commit deploy → rollback cycle and asserts on real file
contents at each step.

### Webhook route
`routes/api.php` (new - Laravel 12's skeleton only wires `web` routes by default),
registered in `bootstrap/app.php`'s `withRouting()`. `POST
/api/webhooks/deploy/{webhookToken}`, unauthenticated by Sanctum/CSRF (API routes
skip both) - see `App\Http\Controllers\DeploymentWebhookController` and
docs/Security.md.

## Module 6 — Laravel Deployment ✅ (as built)

### `deployment_steps`
| Column | Type | Notes |
|---|---|---|
| id | bigint pk | |
| deployment_id | fk deployments, cascadeOnDelete | |
| name | string | e.g. `composer install`, `artisan migrate` |
| status | string, cast to `App\Enums\DeploymentStepStatus` default `pending` | pending/running/success/failed/skipped |
| output | longtext nullable | combined stdout+stderr for that one step |
| order | unsigned int | execution order (0-indexed) |
| started_at / finished_at | timestamp nullable | |
| timestamps | | no soft deletes - same append-only reasoning as `deployments` |

`App\Services\Deployments\LaravelDeploymentPipelineService::run()` creates one row
per step, in order, and halts on the first failure (subsequent steps are simply
never created, not marked "skipped" - `Skipped` exists in the enum for a possible
future opt-out UI, unused today). Only runs for `WebsiteFramework::Laravel`
websites; Plain PHP/Static sites skip the pipeline entirely and a deployment's
success depends solely on `GitDeploymentService`'s git checkout succeeding.

Runs `artisan` via `PHP_BINARY` (the currently-running PHP interpreter), not a
per-website PHP version binary - see docs/Roadmap.md's Module 6 note on why that's
an acceptable simplification for now.

## Module 7 — File Manager ✅ (as built)

No new tables. File/directory entries are read live from each website's
`document_root` on disk via `App\Services\FileManager\FileManagerService` and
represented in memory as `App\DTOs\FileManager\FileEntryData` - there is nothing
to persist, since the filesystem itself is the source of truth. The only
persistent trace of a File Manager action is an `activity('file_manager')` audit
log entry (via the existing `spatie/laravel-activitylog` table from Module 1),
written manually by each `App\Actions\FileManager\*` action since these are
filesystem mutations, not Eloquent model changes `LogsActivity` could observe
automatically.

One new permission: `manage website files` (see docs/Security.md and
`WebsitePolicy::manageFiles()`).

## Module 8 — Terminal ✅ (as built)

### `terminal_sessions`
| Column | Type | Notes |
|---|---|---|
| id | bigint pk | |
| server_id | fk servers, cascadeOnDelete | only the local server exists pre-Module-18 |
| user_id | fk users, cascadeOnDelete | one user's tabs are invisible to/unusable by another - see `runCommand`'s ownership check |
| label | string nullable | e.g. "Tab 1" |
| current_directory | string | updated in place by `cd`; not a website's document root, this is the whole server's filesystem |
| closed_at | timestamp nullable | tabs aren't deleted on close, just marked closed, for history |
| timestamps | | |

### `terminal_commands`
| Column | Type | Notes |
|---|---|---|
| id | bigint pk | |
| terminal_session_id | fk terminal_sessions, cascadeOnDelete | |
| user_id | fk users, cascadeOnDelete | |
| command | text | the raw line the user submitted, including blocked ones |
| output | longtext nullable | combined stdout+stderr |
| exit_code | int nullable | `null` for a blocked command that never ran |
| status | string, cast to `App\Enums\TerminalCommandStatus` default `executed` | executed/blocked |
| executed_at | timestamp nullable | |
| timestamps | | |

Every submitted line gets a row here regardless of outcome - this table **is** the
audit log for this module (no separate `activity()` call per command; that would
be pure duplication of what's already a fully queryable, per-command record). A
generic `activity('terminal')` entry is only written for session-lifecycle events
(open/close), matching `BackupDatabaseAction`'s pattern from Module 4.

One new permission: `use terminal`, admin/super-admin only (see docs/Security.md
and `ServerPolicy::useTerminal()`).

## Module 9 — Cloudflare ✅ (as built)

### `cloudflare_zones`
| Column | Type | Notes |
|---|---|---|
| id | bigint pk | |
| website_id | fk websites, unique, cascadeOnDelete | one zone per website |
| zone_id | string | Cloudflare's own zone identifier |
| api_token | text, cast `encrypted` | this website's own Cloudflare API token - never a shared/global credential |
| ssl_mode | string, cast to `App\Enums\CloudflareSslMode` default `flexible` | off/flexible/full/strict, mirrors Cloudflare's real setting values exactly |
| last_synced_at | timestamp nullable | updated on connect and on a successful SSL mode change |
| timestamps | | |

### `cloudflare_tunnels`
| Column | Type | Notes |
|---|---|---|
| id | bigint pk | |
| server_id | fk servers, cascadeOnDelete | tunnels are server-scoped, not website-scoped |
| cloudflare_tunnel_id | string | Cloudflare's own tunnel identifier |
| name | string | |
| status | string, cast to `App\Enums\CloudflareTunnelStatus` default `inactive` | this app never sets it to `active` itself - see below |
| timestamps | | |

DNS records themselves are **not** persisted locally - they're fetched live from
Cloudflare's API on every page load (`CloudflareZoneService::listDnsRecords()`),
the same "the real system is the source of truth, don't mirror it and risk
drift" principle as Module 7's live filesystem reads.

`cloudflare_tunnels.status` starts and stays `inactive` through everything this
module actually does - creating/destroying the tunnel *object* via Cloudflare's
API never starts a real `cloudflared` connector process on the server, so no
traffic ever flows through it yet. The enum has an `Active`/`Error` case ready
for when a future module (or a manual admin action) actually manages that
process; nothing in Module 9 sets it.

Two new permissions: none for zones/DNS/SSL/cache (these reuse the existing
`update websites` permission via `WebsitePolicy::update()` - a developer who can
edit their own website can also manage its Cloudflare zone); `manage cloudflare
tunnels` (admin/super-admin only, see `ServerPolicy::manageTunnels()`) for
tunnels specifically, since a tunnel reaches the whole server, not one site.

## Module 10 — SSL ✅ (as built)

### `ssl_certificates`
| Column | Type | Notes |
|---|---|---|
| id | bigint pk | |
| website_id | fk websites, cascadeOnDelete | not unique - history is kept, `Website::currentCertificate()` finds the active/expiring one |
| type | string, cast to `App\Enums\CertificateType` default `lets_encrypt` | lets_encrypt/custom |
| domains | json | every domain/SAN this certificate actually covers |
| certificate | longtext nullable | full PEM chain |
| private_key | longtext nullable, cast `encrypted` | |
| issued_at / expires_at | timestamp nullable | parsed from the real certificate via `openssl_x509_parse` |
| status | string, cast to `App\Enums\CertificateStatus` default `pending` | pending/active/expiring/expired/revoked/failed |
| auto_renew | bool default true | only meaningful for `lets_encrypt` - a `custom` cert is never auto-renewed |
| last_renewal_attempt_at / last_error | timestamp/text nullable | |
| timestamps | | |

No new permissions - zone/DNS/SSL/cache-style reasoning applies again here:
issuing/uploading/revoking a certificate reuses the existing `WebsitePolicy::update()`
ability, the same trust level as toggling SSL was already gated at in Module 3.

`SslCertificate::syncWebsiteSslStatus()` keeps `Website::ssl_status` (Module 3)
truthful about whatever the certificate's real status is - it's called after
every mutating SSL action so the website's own status is never left saying
"Active" once the certificate is revoked/expired/failed.

The Let's Encrypt **account** key and account URL (one shared ACME account for
the whole installation, not per-website) are stored as plain files at
`storage/app/acme/account-key.pem` / `account-url.txt`, not in this table -
they're infrastructure for talking to the ACME server, not a certificate record.

### `cron_jobs` (Module 11)
id, server_id, website_id nullable, label, command, schedule (cron expression),
is_enabled, last_run_at, last_exit_code, timestamps.

### `queue_workers` (Module 12)
id, website_id, connection, queue, processes, status (enum: running/stopped/failed),
supervisor_program_name, timestamps.

## Module 13 — Backups ✅ (as built)

### `backups`
| Column | Type | Notes |
|---|---|---|
| id | bigint pk | |
| website_id | fk websites, cascadeOnDelete | |
| created_by | fk users, nullOnDelete, nullable | null = created by the scheduled command, not a person |
| type | string, cast to `App\Enums\BackupType` default `full` | files/database/full/git |
| disk_path | string nullable | absolute path to the zip archive; for `git` backups, `"{bare repo path}#{commit sha}"` instead - see `RestoreBackupAction` |
| size_bytes | unsigned bigint nullable | null for `git` type (no single file to size) |
| status | string, cast to `App\Enums\BackupStatus` default `pending` | pending/running/success/failed |
| error | text nullable | |
| started_at / completed_at | timestamp nullable | |
| timestamps | | |

### `websites` additions
`backups_enabled` (bool, default false), `backup_retention_count` (unsigned int,
default 7) - `app:run-scheduled-backups` (daily) creates a `full` backup for every
website with `backups_enabled = true`, then prunes successful backups beyond
`backup_retention_count`, newest kept.

No new permissions - backup/restore reuses `WebsitePolicy::update()`, same
reasoning as Modules 9/10.

Every backup lives on the **same server's disk** as the website it protects
(`config('mtp.website_backups_path')`/`config('mtp.git_backups_path')`, both
under `storage/`, never inside the website's own document root) - a real
disaster-recovery gap (a full-disk failure takes the backups too) called out
honestly in docs/Features.md, not silently glossed over. Shipping backups
off-server is a natural fit for Module 18 (Multi Server) once a second server
exists to ship them to.

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
