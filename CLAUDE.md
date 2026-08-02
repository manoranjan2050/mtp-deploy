# CLAUDE.md — MTP Deploy

Guidance for an AI assistant (or a new human collaborator) picking up work in this
repository. Read this file first, then [TODO.md](TODO.md) for exactly what's in
flight, then the relevant [docs/](docs) file for the module you're touching.

## What this project is
A self-hosted server management + deployment platform for Laravel/PHP (Forge/Ploi/
CloudPanel-style), built in Laravel 12 + Filament v4. Full mission in
[docs/Vision.md](docs/Vision.md).

## The one rule that matters most
**Build one module at a time, in the order given in [docs/Roadmap.md](docs/Roadmap.md).**
Do not start Module N+1's Filament resources/UI until Module N's migrations, models,
policies, service layer, and tests are in place and green. If you're unsure what's
"in progress" right now, check [TODO.md](TODO.md) — it's kept up to date as the
single source of truth for current state.

## Environment specifics (this machine)
- PHP 8.2.31 via AMPPS at `C:\Program Files\Ampps\php\php.exe` — the spec asked for
  PHP 8.4+, but that isn't installed here. `composer.json` is constrained to `^8.2`
  for now; revisit when 8.4 is available.
- MySQL 8.0.46 (MySQL-protocol compatible with MariaDB) via AMPPS at
  `C:\Program Files\Ampps\mysql\bin\mysqld.exe`, using
  `C:\Program Files\Ampps\mysql\my.ini` as its config. It is **not** registered as a
  Windows service — start it manually if it's not already running:
  ```bash
  cd "/c/Program Files/Ampps/mysql/bin"
  nohup ./mysqld.exe --defaults-file="/c/Program Files/Ampps/mysql/my.ini" > /tmp/mysqld.log 2>&1 &
  ```
  Root has no password. Database name: `mtpdeploy`.
- Redis is **not installed** in this dev environment. `.env` currently uses the
  `database` driver for cache/queue/session. Switch to `redis` once it's available
  locally, and definitely before any production deployment (Supervisor/queue
  behavior at scale assumes Redis).
- Node 24.18.0 / npm 11.16.0 available for the Vite/Tailwind asset build.

## Architecture non-negotiables
- Repository → Service → Action layering, DTOs across boundaries, Enums for every
  fixed value set. Full detail: [docs/Architecture.md](docs/Architecture.md).
- Privileged system operations (nginx reload, php-fpm restart, certbot, mysql admin,
  supervisorctl, SSH to remote servers) go **only** through `SystemCommandService`
  using `Symfony\Process` with a fixed whitelist — never raw shell string
  interpolation, never ad-hoc `sudo` calls scattered through the codebase. See
  [docs/Security.md](docs/Security.md).
- Laravel 12 has **no `EventServiceProvider` auto-discovery** — register
  `Event::listen()` calls explicitly, and never also call `Event::listen()` again
  from inside the listener itself (double-fires it). This has bitten other projects
  in this user's workspace before.

## Where things are tracked
- Module order + status: [docs/Roadmap.md](docs/Roadmap.md)
- Current granular checklist: [TODO.md](TODO.md)
- Full feature list per module: [docs/Features.md](docs/Features.md)
- Schema (grows per module): [docs/Database.md](docs/Database.md)
- Coding conventions + testing bar: [docs/CodingStandards.md](docs/CodingStandards.md)

## When a module is "done"
Per [docs/CodingStandards.md](docs/CodingStandards.md): migrations + models +
policies + service layer + Filament UI + feature tests (happy path *and* an
authorization-denial test per mutating endpoint) + `php artisan test` green +
`vendor/bin/pint` clean + the relevant docs updated + Roadmap status flipped to ✅.
Then, and only then, start the next module.
