# MTP Deploy

**Modern Self Hosted Deployment Platform**

A self-hosted, Forge/Ploi/CloudPanel-style server management and deployment platform
built for Laravel/PHP developers, agencies, freelancers, and home-lab users. Deploy
and manage websites, databases, SSL, cron, queues, and more from a browser — no
manual nginx or shell work required.

See [docs/Vision.md](docs/Vision.md) for the full mission and non-goals.

## Status

🚧 Under active, incremental development — one module at a time. See
[docs/Roadmap.md](docs/Roadmap.md) for module order and [TODO.md](TODO.md) for the
live checklist of what's currently being built.

**Currently in progress: Module 1 — Authentication.**

## Tech Stack

Laravel 12 · PHP 8.2+ (8.4+ targeted, see note below) · Filament v4 · Livewire 3 ·
Alpine.js · Tailwind CSS · MariaDB · Redis · nginx · Supervisor · Cloudflare API ·
Symfony Process · Spatie packages · Chart.js · Monaco Editor · xterm.js

> This dev environment currently has PHP 8.2.31 available (via AMPPS), not 8.4.
> Laravel 12 and Filament v4 fully support 8.2, so development proceeds on it; bump
> `composer.json`'s PHP constraint and re-test before a production PHP 8.4 rollout.

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

## Local Setup

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

## License

Proprietary — all rights reserved (not yet decided; treat as closed-source for now).
