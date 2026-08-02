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

## File Manager / Uploads (Module 7, as built)
- Every operation is scoped to one website's `document_root` via
  `App\Services\FileManager\FileManagerService`, the only class that touches a
  website's files. Two layers of validation, not one: every relative path is
  syntactically rejected *before* resolution (`..` segments, absolute/drive-letter
  paths, null bytes), and the **resolved** `realpath()` is re-checked against the
  document root *after* resolution too - the second check is what actually catches
  symlink escapes, which a syntax-only check on the raw string would miss entirely.
- Unlike a generic multi-tenant upload feature, this module does **not** apply a
  MIME/executable-extension blocklist to uploads - a `.php` file uploaded into a
  website's own document root is exactly the intended, legitimate use case for a
  PHP hosting panel (this is how Forge/Ploi/CloudPanel all behave too). The
  path-traversal protection above is what prevents a file from ever landing
  *outside* that website's own document root in the first place; there is no
  separate directory in this app where an uploaded executable would pose a
  privilege-escalation risk the containment check doesn't already cover.
- Zip extraction (`unzip()`) guards against both:
  - **Zip slip** - a malicious archive entry name containing `../` that would
    otherwise write outside the intended extraction directory. Every entry's
    target path is resolved and re-checked against the destination directory
    before being written, exactly like every other path in this class.
  - **Decompression bombs** - before extracting anything, every entry's
    uncompressed size (via `ZipArchive::statIndex()`) is summed and the archive is
    rejected if that total exceeds 512 MB, or if the uncompressed:compressed
    ratio exceeds 100:1 (the signature of a crafted bomb - genuine mixed-content
    files never compress anywhere near that well).
- Every mutating File Manager action is gated behind `WebsitePolicy::manageFiles()`
  (the `manage website files` permission - `admin`/`super-admin` on every site,
  `developer` only on sites they created, not granted to `viewer`) and manually
  audit-logged (`activity('file_manager')`) alongside the existing activity log,
  since filesystem mutations aren't Eloquent model changes `LogsActivity` can
  observe automatically.

## Transport
- Local dev runs over `http://localhost` for convenience; any real deployment must
  run behind HTTPS (the panel's own SSL, independent of the SSL the panel provisions
  *for managed sites*). `SESSION_ENCRYPT` and secure cookie flags are enabled in
  production `.env`.

## Dependency Hygiene
- `composer audit` / `npm audit` run as part of the CI checklist before a module is
  considered complete (see [CodingStandards.md](CodingStandards.md)).
