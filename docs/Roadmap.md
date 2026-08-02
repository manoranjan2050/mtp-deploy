# Roadmap — MTP Deploy

Modules are built **one at a time, in order**. A module is not "done" until it has
migrations, models, policies, Filament resources/pages, feature tests, and an entry
in [TODO.md](../TODO.md) marked complete.

Status legend: ⬜ Not started · 🟨 In progress · ✅ Complete

| # | Module | Status | Depends on |
|---|--------|--------|------------|
| 1 | Authentication (login, register, 2FA, profile, roles, permissions, sessions, API tokens, activity logs) | ✅ | — |
| 2 | Dashboard (system stats, service status, charts) | ✅ | 1 |
| 3 | Website Manager (vhosts, SSL toggle, clone, suspend, PHP version, logs) | ✅ | 1, 2 |
| 4 | Database Manager (create/drop DB & users, privileges, backup/restore, phpMyAdmin) | ✅ | 1, 3 |
| 5 | Deployment (Git providers, webhook, deploy button, rollback, history) | ✅ | 3 |
| 6 | Laravel Deployment (composer/artisan pipeline steps) | ⬜ | 5 |
| 7 | File Manager (browse/upload/download/zip/edit) | ⬜ | 3 |
| 8 | Terminal (browser SSH via xterm.js) | ⬜ | 1 |
| 9 | Cloudflare (DNS, tunnels, SSL, cache purge) | ⬜ | 3 |
| 10 | SSL (Let's Encrypt, renewal, custom certs, wildcard) | ⬜ | 3, 9 |
| 11 | Cron Manager | ⬜ | 3 |
| 12 | Queue Manager (Supervisor) | ⬜ | 3, 6 |
| 13 | Backups (site + database, scheduled) | ⬜ | 3, 4 |
| 14 | Logs (Laravel/PHP/nginx/MariaDB, search) | ⬜ | 3 |
| 15 | Monitoring (CPU/RAM/disk/temp/bandwidth/processes/alerts) | ⬜ | 2 |
| 16 | Notifications (Telegram/Email/Discord/Slack) | ⬜ | 1 |
| 17 | API (REST + tokens + webhooks) | ⬜ | 1 |
| 18 | Multi Server (remote server connections, groups) | ⬜ | 2, 8 |
| 19 | Docker (containers, images, compose) | ⬜ | 18 |
| 20 | AI Assistant (error explain, deploy suggestions, health, log analysis) | ⬜ | 14, 15 |

## Current Focus
**Module 6 — Laravel Deployment.** Modules 1–5 are complete - 83 passing tests,
Pint clean. See [TODO.md](../TODO.md) for the granular checklist, the stack
deviations made during Module 1 (Filament v5/Livewire 4 instead of v4/3, for a real
unpatched-CVE reason - see docs/Architecture.md), and a recurring class of bug this
project keeps hitting and fixing: Eloquent doesn't hydrate DB column defaults onto a
freshly-created, unrefreshed model instance, so every model with a DB-level
`->default(...)` needs a matching in-memory `protected $attributes = [...]` default
(hit on `User::is_active` in Module 2, `Website::status`/`ssl_status`/`framework` in
Module 3 - watch for this on every new model going forward).

Checklist items deliberately deferred, not forgotten:
- Module 3's "Website logs" - real log tailing is Module 14's job.
- Module 4's "phpMyAdmin integration" - this dev machine doesn't have phpMyAdmin
  installed; a real integration is just a configured launch-link/SSO hand-off, low
  value to fake without a real phpMyAdmin instance to point at. Revisit once one is
  available, or when a real target server is provisioned.
- Module 5 covers "get the right git commit checked out" only - the actual
  Laravel-specific pipeline (`composer install`, `artisan migrate`, etc.) is
  Module 6's job, layered on top of a successful `GitDeploymentService::deploy()`.

Module 4 is also where this project first ran real destructive infrastructure
commands as part of its own test suite (actual `CREATE`/`DROP DATABASE`,
`CREATE`/`DROP USER`, `GRANT`/`REVOKE`, and a genuine `mysqldump`/`mysql` backup and
restore round-trip against this dev machine's local MySQL) rather than working
against files in a temp directory (Module 3) or reading read-only OS state
(Module 2). Every test uses a uniquely-named throwaway database/user and cleans up
in `tearDown()`. Module 5 continued this pattern with real `git` operations against
a local bare-repository fixture standing in for GitHub.

Module 5 also surfaced a bug from Module 3 that had gone unnoticed until now: a
Filament action's `->icon()` closure was type-hinted to return `string` but
actually returned a `Heroicon` enum instance, throwing a `TypeError` the moment the
Websites list page rendered a real row (icon closures are evaluated lazily, only
when Filament applies them to an actual record - no test had rendered the list page
with a row present until Module 5's browser verification did). Fixed, and now
covered by `tests/Feature/Websites/ListWebsitesPageTest.php` - a reminder that
Action/Policy-level tests don't substitute for at least one test per resource that
renders its real list page with a real row.

## Working Agreement
- Do not start a module's Filament resources until its migrations + models + policies
  + service layer exist and are tested.
- Every module ships with feature tests covering the happy path and the policy/authz
  boundary (an unauthorized user cannot do X).
- Update this table's status column and [TODO.md](../TODO.md) at the end of every
  module.
