# MTP Deploy

**A modern, self-hosted server management & deployment platform for Laravel, PHP, and static sites.**

[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Filament](https://img.shields.io/badge/Filament-v5-FDAE4B)](https://filamentphp.com)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://php.net)
[![License](https://img.shields.io/badge/license-Proprietary-lightgrey)](#license)

An open, Forge/Ploi/CloudPanel-style control panel you run on your **own** server —
deploy and manage websites, databases, SSL, Cloudflare, backups, terminals, and more
from a single browser-based dashboard. No manual nginx editing, no SSH gymnastics for
routine tasks.

See [docs/Vision.md](docs/Vision.md) for the full mission, target users, and non-goals.

---

## What's built

| # | Module | Status |
|---|---|:---:|
| 1 | Authentication — login, 2FA, roles/permissions, sessions, API tokens, activity log | ✅ |
| 2 | Dashboard — live system stats, service status, trend charts | ✅ |
| 3 | Website Manager — vhosts, PHP-FPM, SSL toggle, clone/suspend | ✅ |
| 4 | Database Manager — create/drop DBs & users, privileges, backup/restore | ✅ |
| 5 | Deployment — Git providers, webhook, deploy button, rollback, history | ✅ |
| 6 | Laravel Deployment pipeline — composer/artisan steps | ✅ |
| 7 | File Manager — browse/upload/download/zip/edit, path-traversal protected | ✅ |
| 8 | Terminal — browser shell (xterm.js), dangerous-command guard | ✅ |
| 9 | Cloudflare — DNS, tunnels, SSL mode, cache purge | ✅ |
| 10 | SSL — Let's Encrypt (ACME v2), custom certs, auto-renewal, wildcard | ✅ |
| 13 | Backups — files + database + git snapshots, scheduled, restore | ✅ |
| 11 | Cron Manager | ⬜ next |
| 12 | Queue Manager (Supervisor) | ⬜ |
| 14 | Logs (Laravel/PHP/nginx/MariaDB) | ⬜ |
| 15 | Monitoring & alerts | ⬜ |
| 16 | Notifications (Telegram/Email/Discord/Slack) | ⬜ |
| 17 | API (REST + tokens + webhooks) | ⬜ |
| 18 | Multi Server | ⬜ |
| 19 | Docker | ⬜ |
| 20 | AI Assistant | ⬜ |

Full module order and depends-on graph: [docs/Roadmap.md](docs/Roadmap.md). Live,
granular in-progress checklist: [TODO.md](TODO.md).

**Already-built extras beyond the original spec:** an About page with live
update-checking against this repo, and a one-shot `install.sh` for a fresh
Ubuntu/Debian server (see below).

## Highlights

- **Real 2FA** — Filament's built-in TOTP/App authenticator, required for
  admin/super-admin the moment you enable it.
- **Real RBAC** — super-admin / admin / developer / viewer roles, with
  per-website ownership scoping for the `developer` role.
- **Real Let's Encrypt** — a hand-written ACME v2 client (http-01 and
  dns-01/wildcard via Cloudflare), no third-party SaaS certificate service.
- **Real backups** — zip file backups, real `mysqldump`-based database
  backups, and git-snapshot backups, all restorable.
- **Real terminal** — a browser shell backed by real process execution,
  with a confirm-to-run guard on destructive commands.
- **Real infrastructure tests** — this project tests against genuine local
  MySQL, git, composer, and the filesystem wherever possible, rather than
  mocking them away (deviating only for third-party SaaS APIs it can't stand
  up locally — Cloudflare, Let's Encrypt — see [CLAUDE.md](CLAUDE.md)).

## Tech Stack

Laravel 12 · PHP 8.2+ (8.4+ targeted, see note below) · Filament v5.7 (v4 targeted,
see note below) · Livewire v4.3 · Alpine.js · Tailwind CSS · MariaDB · Redis ·
nginx · Supervisor · Cloudflare API · Symfony Process · Spatie packages ·
Chart.js · xterm.js

> This dev environment currently has PHP 8.2.31 available (via AMPPS), not 8.4.
> Laravel 12 and Filament v5 fully support 8.2, so development proceeds on it; bump
> `composer.json`'s PHP constraint and re-test before a production PHP 8.4 rollout.
>
> Filament v5 / Livewire v4 are used instead of the originally-targeted v4/v3: every
> published Livewire 3.x release is blocked by an unpatched RCE advisory
> (CVE-2025-54068 + two related CVEs). See [docs/Architecture.md](docs/Architecture.md)
> for the full explanation.

## Documentation

All architecture and planning docs live in [`docs/`](docs):

| Doc | Purpose |
|---|---|
| [Vision.md](docs/Vision.md) | Mission, target users, principles, non-goals |
| [Roadmap.md](docs/Roadmap.md) | 20-module build order and status |
| [Architecture.md](docs/Architecture.md) | Layered architecture, tech stack, privileged-command model |
| [Database.md](docs/Database.md) | Schema, per module |
| [API.md](docs/API.md) | REST API surface, auth, webhooks |
| [UserFlow.md](docs/UserFlow.md) | End-to-end user journeys |
| [Features.md](docs/Features.md) | Full feature checklist per module |
| [Security.md](docs/Security.md) | AuthN/AuthZ, audit logging, secrets, privileged execution |
| [FolderStructure.md](docs/FolderStructure.md) | Concrete `app/` layout |
| [CodingStandards.md](docs/CodingStandards.md) | Style, naming, testing bar, Laravel 12 pitfalls |

Also see [CLAUDE.md](CLAUDE.md) if you're an AI assistant picking up work on this
repo, and [TODO.md](TODO.md) for the granular in-progress checklist.

## Quick Start — Production

On a fresh Ubuntu/Debian server:

```bash
git clone https://github.com/manoranjan2050/mtp-deploy.git
cd mtp-deploy
sudo ./install.sh
```

`install.sh` installs PHP, MariaDB, Redis, nginx, Supervisor, and phpMyAdmin,
builds the app, and wires up the scheduler/queue worker — safe to re-run if it
fails partway through. See **[INSTALL.md](INSTALL.md)** for the full
step-by-step guide, every configuration variable, and the post-install
checklist (first-account registration, enabling 2FA, issuing a real SSL
certificate).

Once it finishes, visit `/admin` and register — the **first** account you
create is automatically made `super-admin` (registration is only reachable
until that first account exists; after that, new users are created via admin
invite from inside the panel).

## Local Dev Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure `.env` for a local MariaDB/MySQL database named `mtpdeploy`
(`DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=mtpdeploy`),
then:

```bash
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

## Testing

```bash
php artisan test
vendor/bin/pint
```

190+ tests, exercised against real local infrastructure wherever the dev
environment allows it (see [CLAUDE.md](CLAUDE.md) for the two deliberate,
disclosed exceptions: Cloudflare and Let's Encrypt, both third-party services
this environment has no real account/domain to test against live).

## License

Proprietary — all rights reserved (not yet decided; treat as closed-source for now).
