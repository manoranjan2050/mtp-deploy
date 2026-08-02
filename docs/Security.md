# Security — MTP Deploy

This platform executes privileged system operations on behalf of authenticated
users. Security is not an afterthought module — every module built must satisfy
this document before it is considered complete.

## Application-Layer Security
- **CSRF** — all state-changing routes go through Laravel's default CSRF middleware;
  Livewire/Filament handle this automatically for their own requests.
- **XSS** — Blade's `{{ }}` escaping by default everywhere; the Monaco editor and any
  raw HTML preview (vhost config, log viewer) render as read-only text content, never
  `{!! !!}` unescaped interpolation of user/system-derived strings.
- **SQL Injection** — Eloquent/query builder only; no raw string-interpolated SQL.
  Any unavoidable raw SQL uses parameter binding.
- **Rate Limiting** — login, password reset, and 2FA challenge endpoints are
  throttled (`throttle` middleware, keyed by IP+identifier). API tokens are
  throttled per-token. Deploy-triggering endpoints have a stricter limiter to
  prevent deploy-storm abuse.
- **Mass assignment** — every model declares an explicit `$fillable` (never
  `$guarded = []`).

## AuthN / AuthZ
- **Role Based Access Control** via `spatie/laravel-permission`. Roles:
  `super-admin` (full access, only role that can manage other admins),
  `admin` (manage sites/servers/users below admin), `developer` (manage assigned
  sites, no user management), `viewer` (read-only).
- **Policies** back every Filament resource — a `developer` cannot see or act on a
  website they are not assigned to; enforced at the Eloquent query level
  (global scope) as well as the Policy level (defense in depth).
- **2FA** is available to every user and can be **required** organization-wide via a
  setting for the `admin`/`super-admin` roles.
- **API tokens** are scoped by ability string (`websites:read`, `deployments:write`,
  etc.) — a leaked token cannot do more than the scopes it was issued.
- **Registration** is only open while the `users` table is empty (initial admin
  bootstrap). After that, new users are created via admin invite, never public
  self-registration — this is a deployment-management panel, not a public SaaS.

## Audit Logging
- Every Action class that mutates state logs to `activity_log`
  (`spatie/laravel-activitylog`): causer, subject, event name, and a before/after
  property diff where applicable.
- Every privileged shell command executed via `SystemCommandService` is logged
  separately with the exact whitelisted command name invoked, its arguments (with
  any secret values redacted), exit code, and truncated stdout/stderr — before
  execution (intent) and after (result), so a crashed/killed process is still
  attributable.
- Audit logs are append-only from the application's perspective — no UI-level
  delete; retention/pruning is an explicit scheduled job with its own audit entry.

## Secrets
- SSH private keys, database passwords, Cloudflare API tokens, notification channel
  credentials, and 2FA secrets are stored using Laravel's `encrypted` Eloquent cast
  (AES-256-CBC via `APP_KEY`). `APP_KEY` must be treated as a production secret and
  rotated via Laravel's key-rotation support (re-encrypt on rotation), never checked
  into version control.
- `.env` is git-ignored; a `.env.example` documents required keys without values.

## Privileged Command Execution
See [Architecture.md](Architecture.md#privileged-system-operations) for the full
model. Security-relevant constraints, restated:
- No user input is ever concatenated into a shell command string. Every privileged
  operation is a pre-defined script invoked with strictly-typed, validated arguments
  passed as separate `Process` array elements (never `Process::fromShellCommandline`
  with interpolated values).
- The whitelist of invocable scripts is a fixed enum (`SystemCommand`), not a
  dynamic/configurable list.
- The `sudoers` grant (production) is scoped to exact script paths with `NOPASSWD`,
  never `ALL=(ALL) NOPASSWD: ALL`.
- Terminal (Module 8) sessions run as the connecting user's own Unix account where
  possible; if a shared service account is unavoidable, root login over the browser
  terminal is explicitly blocked and destructive command patterns
  (`rm -rf /`, `mkfs`, etc.) require a typed confirmation phrase before execution.

## File Manager / Uploads
- Uploaded files are validated by MIME + extension allowlist; executable extensions
  (`.php`, `.phtml`, etc.) uploaded outside a site's own document root are rejected.
- Zip extraction guards against path traversal (`../`) and zip-bomb style expansion
  ratios before writing to disk.

## Transport
- Local dev runs over `http://localhost` for convenience; any real deployment must
  run behind HTTPS (the panel's own SSL, independent of the SSL the panel provisions
  *for managed sites*). `SESSION_ENCRYPT` and secure cookie flags are enabled in
  production `.env`.

## Dependency Hygiene
- `composer audit` / `npm audit` run as part of the CI checklist before a module is
  considered complete (see [CodingStandards.md](CodingStandards.md)).
