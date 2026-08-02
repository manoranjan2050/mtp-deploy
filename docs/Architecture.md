# Architecture — MTP Deploy

## Tech Stack

| Layer | Choice |
|---|---|
| Language | PHP 8.4+ (built/tested against 8.2 in this dev environment — see note below) |
| Framework | Laravel 12 |
| Admin UI | **Filament v5.7** (spec said v4 — see note below) |
| Reactive UI | **Livewire v4.3** (spec said 3 — see note below) + Alpine.js |
| CSS | Tailwind CSS (via Filament) |
| Database | MariaDB (MySQL-protocol compatible; dev env runs MySQL 8.0.46 via AMPPS) |
| Cache / Queue backing | Redis (falls back to `database` driver until Redis is installed in an environment) |
| Web server (managed sites) | nginx |
| Process supervision (managed sites) | Supervisor |
| DNS / CDN / Tunnels | Cloudflare API |
| Shell execution | Symfony Process (`symfony/process`) |
| Auth / RBAC | Filament's built-in auth pages/actions + `spatie/laravel-permission` |
| Audit trail | `spatie/laravel-activitylog` |
| API auth | Laravel Sanctum |
| 2FA | Filament's **built-in** Multi-Factor Authentication (App/TOTP provider) — not a hand-rolled package, see note below |
| Charts | Chart.js (via a thin Livewire/Alpine wrapper) |
| Code editor (File Manager, vhost editor) | Monaco Editor |
| Terminal | xterm.js + a Laravel Echo/WebSocket bridge to a PTY-over-SSH process |

> **PHP version note:** the spec targets PHP 8.4+. The current dev machine only has
> PHP 8.2.31 available (via AMPPS). Laravel 12 and Filament v5 both fully support
> 8.2, so development proceeds on 8.2 with `composer.json` constrained to `^8.2` for
> now. Bump to `^8.4` and re-test before production deployment once a matching PHP
> runtime is available.

> **Filament v5 / Livewire v4 note:** the spec asked for Filament v4 and Livewire 3.
> Every published Livewire 3.x release (through v3.8.3, the latest) is blocked by
> Composer's security-advisory policy — CVE-2025-54068 (unauthenticated RCE via
> component property hydration) plus two related advisories, none patched in any
> 3.x tag. Filament v5 depends on Livewire ^4.1, which is patched, and is the direct
> successor to Filament v4 from the same maintainer with the same panel/resource
> APIs used throughout this codebase. Shipping a known-vulnerable dependency to
> satisfy an exact version number would contradict this project's own Security.md,
> so v5/v4 was used instead. Filament v4 (`^4.0`) remains installable on its own if
> a future constraint forces a downgrade, but would still require Livewire 3.x and
> therefore the same unpatched RCE exposure.

> **2FA note:** Filament v4/v5 ships a native Multi-Factor Authentication framework
> (`Filament\Auth\MultiFactor\App\AppAuthentication`) with TOTP enrollment (QR code),
> recovery codes, and a panel-level `isRequired` closure — used directly instead of
> integrating `pragmarx/google2fa-laravel` by hand, which would have duplicated
> functionality Filament already provides and tests upstream.

## Layered Architecture

Every module follows the same layering, no exceptions:

```
HTTP / Filament Resource / Livewire Component   (presentation — no business logic)
        │
        ▼
Form Request (validation)  →  Action class (single business operation)
        │                              │
        ▼                              ▼
   Policy (authorization)        Service (orchestrates repositories, external
        │                        processes, jobs)
        ▼                              │
   DTO (typed data crossing layers)     ▼
                                  Repository (Eloquent query boundary)
                                        │
                                        ▼
                                     Model
```

- **Repository Pattern** — one repository per aggregate root (e.g. `SiteRepository`,
  `DatabaseRepository`). Controllers/Livewire components never touch Eloquent
  directly; they call a Service, which calls a Repository.
- **Service Layer** — one service per module concern (e.g. `SiteProvisioningService`,
  `SslService`). Services are stateless, constructor-injected, and are the only place
  that calls into `Symfony\Component\Process\Process` for shell/system operations.
- **Action Classes** — single-purpose, invokable classes for one business operation
  (e.g. `CreateWebsiteAction`, `IssueSslCertificateAction`). Actions are what Jobs and
  Livewire components call; they compose Services + Repositories and fire Events.
- **Jobs** — anything that touches the filesystem, network, SSH, or takes >1s runs as
  a queued Job, never inline in a request.
- **Events / Listeners** — cross-module side effects (e.g. `WebsiteCreated` →
  `ProvisionDefaultSslCertificate` listener) instead of services calling each other
  directly.
- **Policies** — one policy per model, backing both Filament resource authorization
  and manual `Gate::authorize()` calls in Actions.
- **Form Requests** — all user input validation; Livewire components use the same
  Form Request rules via `Illuminate\Support\Facades\Validator`.
- **DTOs** — plain, typed, `readonly` classes for data crossing the
  Action/Service/Job boundary (never pass raw arrays between layers).
- **Enums** — native PHP 8.1+ backed enums for all fixed value sets (site status,
  deployment status, PHP version, database engine, notification channel, etc.).

## Privileged System Operations

The web app process runs as an unprivileged user. It never calls `sudo` ad-hoc from
arbitrary code. Instead:

1. A fixed set of **whitelisted operation scripts** live outside the webroot (e.g.
   `/opt/mtp-deploy/bin/*.sh` in production, `storage/app/system-ops/*` stub scripts
   in local dev).
2. A `SystemCommandService` is the *only* class allowed to construct a
   `Symfony\Component\Process\Process`. It accepts a typed `SystemCommand` DTO/Enum,
   never a raw string built from user input.
3. Each operation is logged (who, what, when, exit code, truncated output) via the
   activity log before and after execution.
4. In production, a narrow `sudoers` entry grants the app user passwordless `sudo`
   only for the specific whitelisted scripts (nginx reload, php-fpm restart,
   certbot, mysql admin socket, supervisorctl) — never a blanket `sudo ALL`.
5. Remote servers (Module 18) are driven the same way, over SSH, using the same
   whitelisted scripts copied to the remote host — the local dev/control-plane
   server is simply "server #1" with a loopback connection.

## Filament Panel Structure

- One primary Filament panel: `admin` (mounted at `/panel` locally; production may
  mount at `/` behind its own subdomain).
- Filament **Resources** map 1:1 to top-level manageable aggregates: `UserResource`,
  `ServerResource` (from Module 18 onward), `WebsiteResource`, `DatabaseResource`,
  `DeploymentResource`, `CronJobResource`, `BackupResource`, etc.
- Filament **Pages** (not Resources) back singleton/dashboard-style screens:
  `Dashboard`, `Terminal`, `FileManager`.
- Custom Livewire components are used where Filament's resource CRUD scaffolding
  doesn't fit (Terminal, File Manager, Deployment log tailing, Monaco editor panes).

## Multi-Server Model (introduced fully in Module 18, designed for from Module 1)

Even though Module 1–17 operate against "this" server, the schema is server-aware
from day one (`servers` table with a `is_local` flag) so Module 18 does not require
a breaking schema migration. See [Database.md](Database.md).

## Directory Structure

See [FolderStructure.md](FolderStructure.md) for the concrete `app/` layout.

## Coding Standards

See [CodingStandards.md](CodingStandards.md).
