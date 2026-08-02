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

## Module 2 — Dashboard
- [ ] CPU usage widget (live, polling)
- [ ] RAM usage widget
- [ ] Disk usage widget
- [ ] Load average widget
- [ ] Network I/O widget
- [ ] PHP version(s) installed
- [ ] MariaDB status (up/down, version, connections)
- [ ] Redis status
- [ ] Nginx status
- [ ] Cloudflare Tunnel status
- [ ] Latest deployments feed
- [ ] Chart.js trend charts (CPU/RAM/disk over time)
- [ ] Dark mode / light mode toggle

## Module 3 — Website Manager
- [ ] Create website (domain, doc root, PHP version)
- [ ] Delete website
- [ ] Suspend website (serve a maintenance page, keep files/DB)
- [ ] Clone website
- [ ] Change PHP version
- [ ] Enable SSL / Disable SSL
- [ ] Restart PHP-FPM pool
- [ ] Restart nginx
- [ ] Website logs (access/error tail)
- [ ] Virtual host editor (Monaco, with safe-mode syntax check before apply)
- [ ] Document root override
- [ ] Domain aliases

## Module 4 — Database Manager
- [ ] Create database
- [ ] Delete database
- [ ] Backup (on-demand)
- [ ] Restore
- [ ] Create database user
- [ ] Manage privileges (per-db grants)
- [ ] phpMyAdmin integration (SSO hand-off or embedded)

## Module 5 — Deployment
- [ ] GitHub connection
- [ ] GitLab connection
- [ ] Bitbucket connection
- [ ] Private repository support (deploy key / PAT)
- [ ] Deploy button (manual trigger)
- [ ] Webhook receiver (HMAC verified)
- [ ] Auto deploy on push
- [ ] Rollback to previous successful deployment
- [ ] Deployment history with full step logs

## Module 6 — Laravel Deployment
- [ ] `composer install` step
- [ ] `composer update` step (opt-in)
- [ ] `artisan optimize`
- [ ] `artisan migrate --force`
- [ ] `artisan queue:restart`
- [ ] `artisan storage:link`
- [ ] `artisan config:cache`
- [ ] `artisan route:cache`
- [ ] `artisan view:cache`
- [ ] File/directory permission fix-up (storage, bootstrap/cache)

## Module 7 — File Manager
- [ ] Upload / Download
- [ ] Rename / Delete
- [ ] Zip / Unzip
- [ ] Drag & drop
- [ ] Monaco code editor for text files
- [ ] Image preview

## Module 8 — Terminal
- [ ] Browser SSH session (xterm.js)
- [ ] Multiple tabs per server
- [ ] Command history per user
- [ ] Root-command protection (confirm-to-run guard for destructive patterns)

## Module 9 — Cloudflare
- [ ] Connect API token
- [ ] Create DNS record
- [ ] Delete DNS record
- [ ] Tunnel management (create/destroy/status)
- [ ] SSL/TLS mode settings
- [ ] Cache purge

## Module 10 — SSL
- [ ] Let's Encrypt issuance
- [ ] Auto-renewal
- [ ] Custom certificate upload
- [ ] Wildcard certificate support (DNS-01 challenge via Cloudflare)

## Module 11 — Cron Manager
- [ ] Create / Edit / Delete cron entries
- [ ] Run Now (ad-hoc execution)
- [ ] Run history with output

## Module 12 — Queue Manager
- [ ] Supervisor program generation per website/queue
- [ ] Restart worker group
- [ ] Failed jobs list
- [ ] Retry failed job
- [ ] Live worker monitor

## Module 13 — Backups
- [ ] Website file backup
- [ ] Database backup
- [ ] Scheduled backup policy per site
- [ ] Download backup archive
- [ ] Restore from backup

## Module 14 — Logs
- [ ] Laravel log viewer (per site, per channel)
- [ ] PHP-FPM error log
- [ ] nginx access/error log
- [ ] MariaDB slow/error log
- [ ] Full-text search across log sources
- [ ] Download raw log file

## Module 15 — Monitoring
- [ ] CPU/RAM/Disk historical graphs
- [ ] Temperature (where sensors are exposed)
- [ ] Bandwidth usage
- [ ] Process list (top-like)
- [ ] Threshold-based alerts

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
