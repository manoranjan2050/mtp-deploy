# Roadmap — MTP Deploy

Modules are built **one at a time, in order**. A module is not "done" until it has
migrations, models, policies, Filament resources/pages, feature tests, and an entry
in [TODO.md](../TODO.md) marked complete.

Status legend: ⬜ Not started · 🟨 In progress · ✅ Complete

| # | Module | Status | Depends on |
|---|--------|--------|------------|
| 1 | Authentication (login, register, 2FA, profile, roles, permissions, sessions, API tokens, activity logs) | ✅ | — |
| 2 | Dashboard (system stats, service status, charts) | ✅ | 1 |
| 3 | Website Manager (vhosts, SSL toggle, clone, suspend, PHP version, logs) | ⬜ | 1, 2 |
| 4 | Database Manager (create/drop DB & users, privileges, backup/restore, phpMyAdmin) | ⬜ | 1, 3 |
| 5 | Deployment (Git providers, webhook, deploy button, rollback, history) | ⬜ | 3 |
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
**Module 3 — Website Manager.** Modules 1 (Authentication) and 2 (Dashboard) are
complete - 33 passing tests, Pint clean. See [TODO.md](../TODO.md) for the granular
checklist, the stack deviations made during Module 1 (Filament v5/Livewire 4 instead
of v4/3, for a real unpatched-CVE reason - see docs/Architecture.md), and a real bug
Module 2 surfaced in Module 1's `User` model (`canAccessPanel()` could throw on a
freshly-created, unrefreshed user - now fixed).

## Working Agreement
- Do not start a module's Filament resources until its migrations + models + policies
  + service layer exist and are tested.
- Every module ships with feature tests covering the happy path and the policy/authz
  boundary (an unauthorized user cannot do X).
- Update this table's status column and [TODO.md](../TODO.md) at the end of every
  module.
