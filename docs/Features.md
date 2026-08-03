# Features — MTP Deploy

Full feature checklist by module. This mirrors [Roadmap.md](Roadmap.md) but at
feature granularity; [TODO.md](../TODO.md) is the live, checkable version of the
module currently in progress.

## Module 1 — Authentication ✅
- [x] Login (email + password, rate-limited, Filament built-in)
- [x] Register (bootstrap-only: allowed while zero users exist, then admin-invite only
      — enforced at both the page and the listener level, see docs/Security.md)
- [x] Forgot Password (Filament built-in signed reset link, no account enumeration)
- [x] Two-Factor Authentication (Filament's built-in App/TOTP MFA provider: enroll,
      confirm, disable, recovery codes; required org-wide for admin/super-admin roles)
- [x] User Profile (name/email/password via Filament's built-in EditProfile page —
      avatar/timezone fields not added, deferred as a small gap, not core to Module 1)
- [x] Roles (super-admin, admin, developer, viewer)
- [x] Permissions (granular, spatie/laravel-permission backed, per-Filament-resource)
- [x] Sessions (list active sessions, revoke one, revoke all others, "this device" flag)
- [x] API Tokens (create scoped token, list, revoke — scopes in `App\Enums\ApiTokenAbility`)
- [x] Activity Logs (searchable/filterable audit trail, read-only resource)

## Module 2 — Dashboard ✅
- [x] CPU usage widget (`SystemStatsOverview`, live via `SystemMetricsService::capture()` — reads `/proc/stat` with a 100ms double-sample; reports "Unavailable" honestly on non-Linux hosts rather than fake data)
- [x] RAM usage widget (`/proc/meminfo`, same widget)
- [x] Disk usage widget (`disk_total_space()`/`disk_free_space()`, cross-platform)
- [x] Load average widget (`sys_getloadavg()`, cross-platform where supported)
- [x] Network I/O (`/proc/net/dev`, summed across non-loopback interfaces — captured into snapshots, not yet its own widget tile)
- [x] PHP version(s) installed (`ServiceStatusWidget`)
- [x] MariaDB status (real `DB::connection()->getPdo()` probe — works on any OS)
- [x] Redis status (real `Redis::ping()` probe, reports Stopped when unreachable/not installed)
- [x] Nginx status (Linux process check via `pgrep`; Unavailable off-Linux)
- [x] Cloudflare Tunnel status (same process-check approach as nginx)
- [x] Latest deployments feed (`LatestDeploymentsWidget` — honest empty-state placeholder; real data ships with Module 5)
- [x] Chart.js trend charts (`MetricsTrendChart`, CPU/RAM over the last 60 `system_metric_snapshots` rows, captured every minute by `app:capture-system-metrics`, scheduled in `routes/console.php`)
- [x] Dark mode / light mode toggle (Filament built-in, present in the topbar since Module 1)

## Module 3 — Website Manager ✅
- [x] Create website (domain, doc root auto-derived, PHP version, framework) -
      `CreateWebsiteAction`, real nginx vhost written + reloaded on save
- [x] Delete website (deprovisions the vhost first, then soft-deletes the record)
- [x] Suspend website (regenerates the vhost as a 503 block instead of the live
      site - a real config swap, not just a DB flag with no effect)
- [x] Clone website (copies document root files + creates a new provisioned site)
- [x] Change PHP version (updates the FPM socket path in the vhost and republishes)
- [x] Enable SSL / Disable SSL (marks intent as `Pending`/`None` - real issuance
      is Module 10's job, which depends on this module; never fabricates an
      `Active` SSL status without a certificate ever having been issued)
- [x] Restart PHP-FPM pool (per-website action, targets that site's PHP version)
- [x] Restart nginx (server-wide action, gated behind `manage website services`
      permission - broader blast radius than a per-site action)
- [ ] Website logs (access/error tail) - **deferred to Module 14** (Logs) by
      design; Module 3 only builds the site, not the log viewer
- [ ] Virtual host editor (Monaco) - **deferred**: the vhost is generated and
      applied automatically by `NginxConfigGeneratorService`/
      `WebsiteProvisioningService`; a manual Monaco-based override editor is a
      follow-up, not required for the create/manage flow to be complete
- [x] Document root override (auto-derived from the domain on create, shown
      read-only on edit - see docs/TODO.md for why it's not directly editable)
- [x] Domain aliases (`TagsInput`, reflected in the vhost's `server_name`)

## Module 4 — Database Manager ✅
- [x] Create database - real `CREATE DATABASE` via `DatabaseManagerService`
      against the `mysql_admin` connection; the metadata row is only created if
      the real statement succeeds (unlike Website, no phantom "intent" records)
- [x] Delete database - real `DROP DATABASE`, then soft-deletes the record
- [x] Backup (on-demand) - real `mysqldump`, credentials passed via a temporary
      `--defaults-extra-file` (never as a CLI argument visible to other
      processes)
- [x] Restore - real `mysql` client fed the uploaded `.sql` via stdin
- [x] Create database user - real `CREATE USER`
- [x] Manage privileges (per-db grants) - real `GRANT`/`REVOKE`, admin-only
      (developers can create/delete databases for their own sites but cannot
      grant/revoke - see docs/Security.md)
- [ ] phpMyAdmin integration - **deferred**: no phpMyAdmin instance exists to
      integrate with on this dev machine; would just be a configured
      launch-link/SSO hand-off once a real target is available

## Module 5 — Deployment ✅
- [x] GitHub / GitLab / Bitbucket - implemented generically via a plain git
      repository URL (`Website::repository_url`) rather than provider-specific
      OAuth "connect your account" flows; since all three speak the same git
      protocol and webhook HMAC scheme, one implementation covers all three.
      A native "browse your GitHub repos" OAuth connector is a reasonable
      future enhancement, not built here.
- [~] Private repository support - works if the target server's SSH key is
      already configured for the git host (a real SSH URL clones exactly like
      a public one); there's no in-panel "generate/manage a deploy key" UI yet
- [x] Deploy button (manual trigger) - `WebsitesTable`'s Deploy action
- [x] Webhook receiver (HMAC verified) - `DeploymentWebhookController`,
      per-website token in the URL + optional `X-Hub-Signature-256`
      verification
- [x] Auto deploy on push - gated behind the website's `auto_deploy` toggle
- [x] Rollback to previous successful deployment - `RollbackDeploymentAction`,
      re-checks-out the exact prior commit as a new, distinctly-marked
      deployment (never mutates history)
- [x] Deployment history with full step logs - `DeploymentResource`, every git
      command and its output appended to the deployment's `log`

## Module 6 — Laravel Deployment ✅
- [x] `composer install` step
- [ ] `composer update` step (opt-in) - **not built**; only the standard
      `composer install` runs today, no UI toggle for an update variant
- [x] `artisan optimize` equivalent - implemented as separate, individually
      tracked steps (`config:cache`/`route:cache`/`view:cache`) rather than one
      opaque `artisan optimize` call, so a failure shows exactly which cache
      step broke instead of just "optimize failed"
- [x] `artisan migrate --force`
- [x] `artisan queue:restart`
- [x] `artisan storage:link`
- [x] `artisan config:cache`
- [x] `artisan route:cache`
- [x] `artisan view:cache`
- [ ] File/directory permission fix-up (storage, bootstrap/cache) - **not
      built**; meaningless to test on this Windows dev box (Unix file modes
      don't apply) and not yet implemented even as a Linux-conditional step

## Module 7 — File Manager
- [x] Upload / Download
- [x] Rename / Delete
- [x] Zip / Unzip (per-item zip, per-archive unzip; both guarded against
      zip-slip and decompression bombs - see docs/Security.md)
- [ ] Drag & drop - **not built**; upload is a standard `<input type="file">`
      via Livewire's file upload feature, not a drag-and-drop zone. Revisit as
      a pure front-end enhancement if needed - the upload path underneath is
      already in place.
- [ ] Monaco code editor for text files - **deferred**; a plain `<textarea>`
      is used instead (consistent with keeping this module's scope to backend
      correctness + security first). Swapping in Monaco later is additive,
      not a rework.
- [x] Image preview (`FileEntryData::isImage()` identifies previewable
      extensions; browser renders the downloaded file natively)
- [ ] Multi-select for zipping several files into one archive at once - **not
      built**; zip is per-item (zip this one file/folder) rather than an
      arbitrary multi-selection. A real multi-select UI is additive later if
      needed.

## Module 8 — Terminal
- [x] Browser shell session (xterm.js) - **scoped as one-shot command execution
      per line, not a true PTY/WebSocket bridge**; see CLAUDE.md for why. Real
      xterm.js renders the UI; `cd` is special-cased so navigating directories
      feels continuous even though each command is its own fresh process.
      "SSH" in the original feature name specifically implied a remote
      server - that's Module 18 (Multi Server)'s job; for now this runs
      against the local server only, which is honest given only a local
      server exists in this app pre-Module-18.
- [x] Multiple tabs per server (multiple `TerminalSession` rows per user, each
      with its own independent current directory and xterm.js instance)
- [x] Command history per user (`terminal_commands` table - one row per
      submitted line, including blocked ones)
- [x] Root-command protection (confirm-to-run guard for destructive patterns -
      `DangerousCommandGuard`'s fixed regex list; typing "yes" re-runs the
      exact blocked command, anything else cancels it)

## Module 9 — Cloudflare
- [x] Connect API token (per-website zone ID + token, entered through the UI,
      stored encrypted on `cloudflare_zones`)
- [x] Create DNS record
- [x] Delete DNS record
- [x] Tunnel management (create/destroy; **status is Cloudflare-reported
      metadata only** - see below) - account-scoped, admin/super-admin only,
      on a dedicated `Cloudflare Tunnels` page (not per-website)
- [x] SSL/TLS mode settings (Off/Flexible/Full/Full Strict)
- [x] Cache purge (purge-everything; per-file purge supported at the service
      layer, not yet exposed in the UI)
- [ ] Actually running the `cloudflared` connector daemon on the server so a
      created tunnel carries real traffic - **not built**; this module
      orchestrates the tunnel *object* via Cloudflare's API and this panel's
      own record of what it created, not the local connector process. See
      CLAUDE.md.

## Module 10 — SSL
- [x] Let's Encrypt issuance - a hand-written minimal RFC 8555 (ACME v2)
      client (`App\Services\Ssl\AcmeClient`), since no existing PHP ACME
      library was dependency-compatible with this project's Laravel
      12/Guzzle 7 stack. **Cannot be verified end-to-end in this dev
      environment** - see CLAUDE.md/docs/Security.md.
- [x] Auto-renewal (`app:renew-ssl-certificates`, scheduled daily; renews any
      Let's Encrypt cert within `mtp.ssl_renewal_threshold_days` of expiry)
- [x] Custom certificate upload (fully real/testable - cert/key match
      validation, domain/expiry extraction via `openssl_x509_parse`)
- [x] Wildcard certificate support (DNS-01 challenge via the Cloudflare zone
      connected in Module 9)
- [x] nginx vhost now emits a real `listen 443 ssl` block + an http→https
      redirect once a website's SSL status is Active

## Module 11 — Cron Manager
- [x] Create / Edit / Delete cron entries (real cron expression validation via
      `dragonmantank/cron-expression`, already a transitive Laravel dependency)
- [x] Run Now (ad-hoc execution) - a genuine one-shot process run, same
      pattern as Module 8's Terminal
- [x] Run history with output - `last_run_at`/`last_exit_code`/`last_output`
      per job (one row per job, not a full run log table - a deliberate
      simplification; see docs/Roadmap.md)
- [x] Every enabled job is synced into the server's **real** system crontab
      under a clearly-marked block, never touching anything a human or
      another tool added by hand outside it - **honestly unavailable on this
      Windows dev box** (no `crontab` binary), same "never fake server state"
      principle as Module 2's `SystemMetricsService`

## Module 12 — Queue Manager
- [x] Supervisor program generation per website/queue (real config file,
      written and removed for real - see `SupervisorConfigGeneratorService`)
- [x] Start/stop/restart worker group - real `supervisorctl` calls,
      **honestly unavailable on this Windows dev box** (no `supervisorctl`
      binary), same principle as Module 11's crontab sync
- [ ] Failed jobs list / retry failed job - **not built**; this dev
      environment has no real, running queue worker to generate failed jobs
      against (the `failed_jobs` table exists via Laravel's own migration,
      but no UI reads it yet). Revisit once a real worker can be exercised.
- [ ] Live worker monitor - **not built**; status is only refreshed on
      create/start/stop/restart, not polled continuously. A real-time view
      would need the same live-metrics widget pattern as Module 2's
      Dashboard.

## Module 13 — Backups
- [x] Website file backup (real zip archive of the document root)
- [x] Database backup (reuses Module 4's real mysqldump-based `DatabaseBackupService`)
- [x] Full backup (files + every database attached to the website, bundled
      into one archive) and a git-snapshot backup (real commits to a bare
      "shadow" git repository per website, independent of any deployment repo)
- [x] Scheduled backup policy per site (`backups_enabled` + `backup_retention_count`
      on `websites`; `app:run-scheduled-backups` runs daily, prunes beyond
      the retention count)
- [x] Restore from backup (files, database, full, and git snapshot - each
      restores real data, verified in tests via genuine corrupt-then-restore
      round trips)
- [ ] Download backup archive from the browser - **not yet exposed in the
      UI** (the file exists on disk at `Backup::disk_path`; a controller
      route to stream it is a small addition, deferred for now)
- [ ] Off-server backup destinations (S3, remote SSH, etc.) - **not built**;
      every backup currently lives on the same server's disk as the website
      it protects, which is a real gap for genuine disaster recovery (a
      full-disk failure takes the backups with it). Revisit if/when Module 18
      (Multi Server) makes a second server available to ship backups to.

## Module 14 — Logs
- [x] Laravel log viewer (per site) - `ManageLogs` Filament page, real
      `SplFileObject`-based tail (last 300 lines) of the site's own
      `storage/logs/laravel.log`
- [x] nginx access/error log (per site) - same page, using the exact path
      `NginxConfigGeneratorService` writes into the vhost config
- [x] Application-wide log viewer - `ApplicationLog` Filament page for MTP
      Deploy's own `storage/logs/laravel.log`, gated by a new
      `view application logs` permission (admin/super-admin only)
- [x] Full-text search across the active log source - case-insensitive
      substring search, capped at 300 matches
- [ ] PHP-FPM error log - **not built**; this dev environment has no
      `php-fpm` process/log path to point at (AMPPS runs PHP differently).
      `WebsiteLogSourceResolver`'s source list is designed to grow, so adding
      it is a small addition once a target path convention is settled.
- [ ] MariaDB slow/error log - **not built**; same reasoning, revisit once a
      real MariaDB log path convention is settled for a production server.
- [ ] Download raw log file - **not built**; only tail/search is exposed in
      the UI so far, same deferred pattern as Module 13's "download backup
      archive."

## Module 15 — Monitoring
- [x] CPU/RAM/Disk historical graphs - extended Module 2's `MetricsTrendChart`
      (Dashboard widget) with a third Disk % dataset, reusing the same
      `system_metric_snapshots` captured every minute
- [x] Bandwidth usage - a new `Monitoring` page computes real rx/tx
      **rate** (bytes/sec) from the delta between consecutive snapshots'
      cumulative counters, shown as a table of the last 20 intervals
- [x] Process list (top-like) - `ProcessListService` runs real `ps -eo
      pid,ppid,pcpu,pmem,etime,comm --sort=-pcpu`, top 30 by CPU -
      **honestly unsupported on this Windows dev box** (no `ps` binary),
      same "never fake server state" principle as `SystemMetricsService`
- [x] Threshold-based alerts - per-server CPU/memory/disk % thresholds
      (`servers.cpu_alert_threshold` etc., null = disabled); `app:capture-
      system-metrics` evaluates them every minute via
      `AlertEvaluatorService`, recording an `Alert` row when breached and
      auto-resolving it once the metric recovers; visible/resolvable on the
      `Monitoring` page
- [ ] Temperature (where sensors are exposed) - **not built**; there is no
      standard, cross-distro sensor path without `lm-sensors` installed and
      configured per-hardware, which is out of scope for a generic
      first pass. Revisit if a concrete target server's sensor layout is
      known.
- [ ] Outbound alert notifications (email/Telegram/etc.) - **not built
      here on purpose**; alerts are recorded and visible in-app only for
      now. Dispatching them through a real notification channel is
      explicitly Module 16's job, not duplicated here.

## Module 16 — Notifications
- [ ] Telegram channel
- [ ] Email channel
- [ ] Discord channel
- [ ] Slack channel
- [ ] Per-event channel routing preferences

## Module 17 — API
- [ ] Full REST API (see [API.md](API.md))
- [ ] Token-based auth with scoped abilities
- [ ] Outbound webhooks with HMAC signatures + retry

## Module 18 — Multi Server
- [ ] Connect a remote server (SSH key exchange, health check)
- [ ] Deploy to a specific remote server
- [ ] Server groups (tag-based)

## Module 19 — Docker
- [ ] Container list/start/stop/restart
- [ ] Image pull/remove
- [ ] Compose stack management

## Module 20 — AI Assistant
- [ ] Explain an error (paste or auto-attached log excerpt)
- [ ] Deployment failure triage suggestions
- [ ] Server health summary in plain English
- [ ] Log anomaly analysis
