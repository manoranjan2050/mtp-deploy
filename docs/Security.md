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
- Terminal (Module 8) is a deliberately different trust model from the above -
  see the dedicated section below.

## Terminal (Module 8, as built)
- Unlike `SystemCommandService`'s fixed-whitelist model (used for automated,
  unattended privileged operations), the Terminal module is, by design, arbitrary
  shell access - that's the entire point of a "Terminal" feature in a
  Forge/Ploi-style panel, the same trust boundary as a real SSH session. It is
  **not** gated by `WhitelistedOperation`; it is gated by **who can reach it at
  all**.
- `ServerPolicy::useTerminal()` restricts the feature to `admin`/`super-admin`
  only - not `developer`, not `viewer`. Unlike Website/Database policies, this is
  not scoped to "sites I created": a server shell reaches every site and every
  database on that server, which is far beyond what a developer role is trusted
  with.
- `DangerousCommandGuard` is a confirm-before-you-nuke-it safety net (a fixed,
  auditable regex list - `rm -rf /`, `mkfs`, a `dd ... of=/dev/...`, the classic
  fork bomb, `DROP DATABASE`/`DROP TABLE`/`TRUNCATE`, `shutdown`/`reboot`, and
  Windows equivalents), not a security boundary in the allowlist sense - an admin
  can always type "yes" and run the command anyway. It exists to catch a
  fat-fingered destructive command, not to prevent a trusted admin from doing
  their job.
- Every submitted command (executed or blocked) is persisted to
  `terminal_commands` - this **is** the audit log for this module, queryable per
  user/session, not a separate generic activity-log entry per line (see
  docs/Database.md).
- **Scope note**: this is one-shot command execution per line (`cd` specially
  intercepted to persist a working directory across commands), not a real
  interactive PTY with a live shell environment - see CLAUDE.md for the full
  reasoning. There is no exported-variable persistence between commands, which is
  a real (if narrow) capability gap versus a genuine SSH session, not a security
  gap - each command still runs as a fresh process under the same trust boundary.

## Cloudflare (Module 9, as built)
- Every website's Cloudflare API token is entered per-website through the UI and
  stored **encrypted** on `cloudflare_zones.api_token` (Laravel's `encrypted`
  cast) - never a single shared/global credential covering every managed site.
  This limits the blast radius of a compromised token to that one website's zone.
- Zone/DNS/SSL/cache actions are gated behind the existing `WebsitePolicy::update()`
  ability, not a new permission - a developer who can already edit their own
  website can also manage its Cloudflare zone (the same trust level as toggling
  SSL or triggering a deployment, both already `update`-gated). Tunnels are a
  separate, stricter case: `ServerPolicy::manageTunnels()` (admin/super-admin
  only, new `manage cloudflare tunnels` permission), since a tunnel reaches the
  whole server, not one site - mirroring `useTerminal()`'s reasoning from Module 8.
- **This dev environment has no real Cloudflare account/zone/API token** -
  unlike every module since Module 4, which exercised genuine local
  infrastructure, Cloudflare is a third-party SaaS requiring real credentials
  this environment doesn't have. `CloudflareApiClient` is tested against
  `Http::fake()` responses shaped exactly like Cloudflare's real, documented API
  v4 envelope (`{success, errors, result}`), which proves the integration code's
  correctness (request shape, auth header, response parsing, error surfacing) but
  **not** a live account round-trip. Treat this module as needing one manual
  smoke test against a real zone before production use - a deliberate, disclosed
  gap, not a silently-skipped one.
- Tunnel creation/destruction only orchestrates the Cloudflare-side tunnel
  *object* - it does not install or run the real `cloudflared` connector
  process on the server. A tunnel created here carries no traffic until that
  connector is actually started, exactly like a real Cloudflare tunnel with no
  connector attached; `cloudflare_tunnels.status` reflects this honestly by
  never being set to `Active` by this module.

## SSL (Module 10, as built)
- Every certificate's private key is stored **encrypted** on `ssl_certificates.private_key`
  (Laravel's `encrypted` cast), same principle as Module 9's Cloudflare API tokens.
- A hand-written minimal ACME v2 client (`App\Services\Ssl\AcmeClient`) is used
  instead of an existing PHP ACME library, because `acmephp/core` (the standard
  choice) is incompatible with this project's Laravel 12/Guzzle 7 dependency
  tree - see CLAUDE.md. It implements only the happy-path RFC 8555 flow needed
  to issue a certificate (account registration, order, http-01/dns-01 challenge,
  finalize, download) - no account key rollover, no external account binding,
  limited retry-on-badNonce handling. Treat it as a narrowly-scoped client, not
  a general-purpose ACME library.
- **This dev environment cannot complete a real issuance** - Let's Encrypt
  validates domain control by connecting back to a public IP/domain, which
  doesn't exist here. `config('mtp.acme_directory_url')` defaults to Let's
  Encrypt's **staging** directory specifically so that any real-world trial run
  hits no rate limits and issues certs signed by an untrusted test CA - do not
  point this at the production directory
  (`https://acme-v02.api.letsencrypt.org/directory`) without understanding
  Let's Encrypt's rate limits first. Every ACME interaction is tested via
  `Http::fake()` against real, documented ACME v2 response shapes, and the JWS
  signing is verified by actually checking the signature against the account's
  real public key (`AcmeClientTest`) - not just asserting a header exists.
- Custom certificate uploads are validated before being trusted: the certificate
  must actually parse as X.509 (`openssl_x509_parse`) and its private key must
  actually match it (`openssl_x509_check_private_key`) - a mismatched pair is
  rejected outright, never silently stored.
- Issuing/uploading/revoking a certificate reuses `WebsitePolicy::update()` (no
  new permission) - the same trust level Module 3 already gated SSL toggling at.

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
