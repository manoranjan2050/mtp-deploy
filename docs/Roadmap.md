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
| 6 | Laravel Deployment (composer/artisan pipeline steps) | ✅ | 5 |
| 7 | File Manager (browse/upload/download/zip/edit) | ✅ | 3 |
| 8 | Terminal (browser SSH via xterm.js) | ✅ | 1 |
| 9 | Cloudflare (DNS, tunnels, SSL, cache purge) | ✅ | 3 |
| 10 | SSL (Let's Encrypt, renewal, custom certs, wildcard) | ✅ | 3, 9 |
| 11 | Cron Manager | ✅ | 3 |
| 12 | Queue Manager (Supervisor) | ✅ | 3, 6 |
| 13 | Backups (site + database, scheduled) | ✅ | 3, 4 |
| 14 | Logs (Laravel/PHP/nginx/MariaDB, search) | ✅ | 3 |
| 15 | Monitoring (CPU/RAM/disk/temp/bandwidth/processes/alerts) | ✅ | 2 |
| 16 | Notifications (Telegram/Email/Discord/Slack) | ✅ | 1 |
| 17 | API (REST + tokens + webhooks) | ✅ | 1 |
| 18 | Multi Server (remote server connections, groups) | ✅ | 2, 8 |
| 19 | Docker (containers, images, compose) | ⬜ | 18 |
| 20 | AI Assistant (error explain, deploy suggestions, health, log analysis) | ⬜ | 14, 15 |

## Current Focus
**Module 19 — Docker.** Modules 1–18 are all complete -
307 passing tests, Pint clean. See [TODO.md](../TODO.md) for the granular checklist, the stack deviations
made during Module 1 (Filament v5/Livewire 4 instead of v4/3, for a real
unpatched-CVE reason - see docs/Architecture.md), and a recurring class of bug this
project keeps hitting and fixing: Eloquent doesn't hydrate DB column defaults onto a
freshly-created, unrefreshed model instance, so every model with a DB-level
`->default(...)` needs a matching in-memory `protected $attributes = [...]` default
(hit on `User::is_active` in Module 2, `Website::status`/`ssl_status`/`framework` in
Module 3 - watch for this on every new model going forward).

Module 7 (File Manager) is the first module that is entirely filesystem-facing
rather than DB/process-facing, and the highest path-traversal risk surface in the
app so far - see CLAUDE.md's dedicated section on `FileManagerService`'s two-layer
validation (syntactic rejection *before* resolution, a re-checked `realpath()`
containment test *after*), and the zip-slip/decompression-bomb guards on `unzip()`.
It also surfaced a Livewire-specific lesson worth remembering for any future
Livewire component: a public property typed as a `Collection` of custom DTOs fails
at render time ("Property type not supported in Livewire") because Livewire's synth
system only (de)hydrates specific types. Use a `#[Computed]` method instead of a
public property for any derived, non-serializable value.

**Module 8 (Terminal) is deliberately scoped as one-shot command execution, not a
true PTY/WebSocket bridge** - see CLAUDE.md for the full reasoning. In short: a
real interactive PTY needs a long-lived process manager outside PHP-FPM/`artisan
serve`'s normal request lifecycle (a Node sidecar with `node-pty`, or a full
WebSocket daemon), which is out of proportion for what's buildable and testable in
this single-machine dev environment right now. Instead, each submitted command runs
as a fresh `Symfony\Process`, with `cd` specially intercepted to update the
session's stored working directory - genuinely functional and fully testable, but
honest that there's no persisted shell environment (exported variables don't carry
between commands) and no raw keystroke echo. Also surfaced two more lessons: (1)
the same `#[Computed]`-not-a-public-property Livewire rule from Module 7 applies to
any array/collection of Eloquent models too, not just DTOs; (2) a `wire:ignore`'d
Alpine component can have its `x-init` invoked more than once for the same DOM
element (Livewire's morph hook and Alpine's own observer can both process an
inserted node), so any one-time-setup JS bridge needs its own idempotency guard at
the plain-DOM level, not just inside Alpine's `x-data` state.

**Module 9 (Cloudflare) is the first module to break the "real infrastructure over
mocks" testing pattern used since Module 4** - and deliberately so. Cloudflare is a
third-party SaaS requiring a real paid/free account, a real domain zone, and real
API credentials that simply don't exist in this dev environment (unlike MySQL,
git, composer, or the local filesystem, none of which needed anyone's account to
stand up locally). Tests use `Http::fake()` against Cloudflare's real, documented
API v4 request/response shapes (the `{success, errors, result}` envelope) instead -
honest about testing the integration code's correctness, not a live account
round-trip. See CLAUDE.md for the full reasoning and the standing recommendation
to do one manual smoke test against a real zone before relying on this in
production. Tunnels only orchestrate the Cloudflare-side tunnel *object* - actually
running the `cloudflared` connector daemon on the server (so real traffic flows
through it) is a deliberate scope gap, also documented in CLAUDE.md.

**Module 10 (SSL)** hand-writes a minimal RFC 8555 (ACME v2) client
(`App\Services\Ssl\AcmeClient`) rather than pulling in an existing PHP ACME
library - `acmephp/core` (the standard choice) is fundamentally incompatible
with Laravel 12's Guzzle 7/psr-http-message 2.0 dependency tree and would
require downgrading core HTTP packages the whole app depends on. The hand-written
client covers only the happy path needed for this panel (no account key
rollover, limited retry-on-badNonce handling) and, like Module 9, cannot be
verified end-to-end here - Let's Encrypt validates domain control by connecting
back to a public IP/domain that doesn't exist in this sandbox. Every ACME
interaction is tested via `Http::fake()` against real ACME v2 response shapes,
**and** the JWS signing itself is verified by actually checking the produced
signature against the account's real public key (not just asserting a header
exists) - see CLAUDE.md. Also worth noting for any future OpenSSL work: this
specific dev machine's PHP build has no working default `openssl.cnf` wired
into php.ini, so every `openssl_pkey_new()`/`openssl_csr_new()` call needs an
explicit `config` path (`config('mtp.openssl_config_path')`) or it fails
outright - not an issue on a real Linux server.

Checklist items deliberately deferred, not forgotten:
- Module 3's "Website logs" - real log tailing is Module 14's job.
- Module 4's "phpMyAdmin integration" - this dev machine doesn't have phpMyAdmin
  installed; a real integration is just a configured launch-link/SSO hand-off, low
  value to fake without a real phpMyAdmin instance to point at. Revisit once one is
  available, or when a real target server is provisioned.
- Module 6's pipeline runs `artisan` via the *currently-running PHP interpreter*
  (`PHP_BINARY`), not a per-website PHP version binary - correct until Module 18
  (Multi Server) needs to run this over SSH against a server with several PHP-FPM
  versions installed side by side.

Module 4 is also where this project first ran real destructive infrastructure
commands as part of its own test suite (actual `CREATE`/`DROP DATABASE`,
`CREATE`/`DROP USER`, `GRANT`/`REVOKE`, and a genuine `mysqldump`/`mysql` backup and
restore round-trip against this dev machine's local MySQL) rather than working
against files in a temp directory (Module 3) or reading read-only OS state
(Module 2). Every test uses a uniquely-named throwaway database/user and cleans up
in `tearDown()`. Module 5 continued this pattern with real `git` operations against
a local bare-repository fixture standing in for GitHub. Module 6 continued it
again with a real `composer install` (a throwaway `composer.json` with no
dependencies, so no network access needed) and the real PHP interpreter running a
fake `artisan` script - proving the pipeline's ordering, output capture, and
stop-on-first-failure behavior without needing a genuine Laravel install as a
fixture.

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
