# CLAUDE.md — MTP Deploy

Guidance for an AI assistant (or a new human collaborator) picking up work in this
repository. Read this file first, then [TODO.md](TODO.md) for exactly what's in
flight, then the relevant [docs/](docs) file for the module you're touching.

## What this project is
A self-hosted server management + deployment platform for Laravel/PHP (Forge/Ploi/
CloudPanel-style), built in Laravel 12 + Filament v5.7 (the spec said v4 — bumped to
v5 for a real unpatched-CVE reason, see docs/Architecture.md). Full mission in
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
  Root has no password but only had a `localhost` grant, not `127.0.0.1` — Laravel's
  TCP connection needs a real grant, so `root@127.0.0.1` was added too (no
  password, matching `root@localhost`). A dedicated `mtpdeploy` app user (not root)
  was created for both hosts with password `mtpdeploy_local_dev`; that's what
  `.env`'s main `mysql` connection uses. Database name: `mtpdeploy`.
- **Module 4 added a second, more-privileged `mysql_admin` connection**
  (`config/database.php`, `DB_ADMIN_*` env vars) that uses `root@127.0.0.1` -
  the app's own `mysql` connection only has grants on its own `mtpdeploy`
  database, not enough to provision/drop *other* databases and users. Every
  `DatabaseManagerService`/`DatabaseBackupService` test genuinely exercises
  this connection against this machine's real MySQL (not mocked) - see the
  note further down on the two PDO/MySQL gotchas that surfaced doing that.
  `MYSQLDUMP_PATH`/`MYSQL_CLI_PATH` env vars point at the real AMPPS
  `mysqldump.exe`/`mysql.exe`.
- Redis is **not installed** in this dev environment. `.env` currently uses the
  `database` driver for cache/queue/session. Switch to `redis` once it's available
  locally, and definitely before any production deployment (Supervisor/queue
  behavior at scale assumes Redis).
- Node 24.18.0 / npm 11.16.0 available for the Vite/Tailwind asset build.
- **This machine is Windows, not Linux.** `App\Services\System\SystemMetricsService`
  and `ServiceStatusService` (Module 2) read `/proc/*` and run `pgrep` - both are
  Linux-only and correctly report an honest "unsupported"/`Unavailable` state here
  rather than fake data. Don't "fix" that by mocking fake numbers for this OS; it's
  intentional (see docs/Vision.md's "never lie about server state" principle). The
  `system_metric_snapshots` table will simply hold `is_supported = false` rows until
  this runs on Linux, or until the code is unit-tested on a Linux CI runner.
- The dashboard's live-metrics widgets are Livewire components lazy-loaded via
  Alpine's `x-intersect` (viewport visibility), not on `wire:init`. If you're
  driving a headless/non-visually-composited browser session, they'll show
  "Loading..." forever because the IntersectionObserver never fires - that's an
  automation limitation, not a bug. Verify with `Livewire::test(WidgetClass::class)`
  feature tests instead (see `tests/Feature/Dashboard/DashboardWidgetsTest.php`),
  or a real, on-screen browser.
- The scheduler (`routes/console.php`) isn't running by default under `php artisan
  serve` - run `php artisan schedule:work` alongside it (or `schedule:run` in a
  loop) to actually populate `system_metric_snapshots` every minute locally.
- `App\Enums\WhitelistedOperation` (Module 3) covers `nginx`/`systemctl` commands
  that don't exist on Windows - `SystemCommandService::run()` still actually
  attempts the `Process` call (no OS branch) and reports the real failure via
  `SystemCommandResult::successful = false`, same honesty principle as the metrics
  services. `WebsiteProvisioningService`'s file writes (vhost configs, document
  roots) go through `config('mtp.*')` paths, not hardcoded `/etc/nginx` - tests
  point these at a temp directory (see
  `tests/Feature/Websites/WebsiteProvisioningServiceTest.php`). If you manually
  click through the panel here, `config('mtp.nginx_sites_available_path')`
  defaults to the real `/etc/nginx/sites-available`, which on Windows resolves to
  `C:\etc\nginx\sites-available` (harmless, but clean it up - it's not a real
  system path here). **Module 5's `config('mtp.sites_root')` (default
  `/var/www`) has the identical quirk** - a manual (non-test) deploy against the
  default config resolves to a Windows path relative to the current process's
  working directory, not a real `/var/www`. Same story: harmless locally, tests
  already override it to a temp directory, don't chase it as a bug.
- Real `git` is available on this machine (`git version 2.54.0`) and is used
  directly - `GitDeploymentService` isn't OS-gated the way the metrics services
  are, since `git` itself is cross-platform. Tests point `repository_url` at a
  real local bare repository fixture (`git init --bare`) instead of a live
  GitHub/GitLab/Bitbucket remote - see
  `tests/Feature/Deployments/GitDeploymentServiceTest.php`.
- Real `composer` is also available (`C:\ProgramData\ComposerSetup\bin`).
  `LaravelDeploymentPipelineService` (Module 6) uses `PHP_BINARY` for `artisan`
  calls - the currently-running interpreter, not a per-website PHP version -
  and runs against a trivial throwaway `composer.json` (no dependencies) plus a
  fake `artisan` script in tests, so nothing needs network access or a genuine
  Laravel install to verify the pipeline's ordering/output-capture/
  stop-on-first-failure behavior. Any `WebsiteFramework::Laravel` fixture used
  in a *different* module's test (e.g. Module 5's git tests) now also triggers
  this pipeline automatically after a successful checkout - if that fixture
  repo has no real `composer.json`/`artisan`, the deployment will report
  `Failed` even though the git operation itself succeeded. Use
  `WebsiteFramework::PlainPhp` for tests that aren't specifically about the
  Laravel pipeline (see `GitDeploymentServiceTest`'s `makeWebsite()`).
- This session's browser-automation limitations (documented above for the
  dashboard's `x-intersect` widgets) also affect Filament's confirmation-modal
  actions (`->requiresConfirmation()`) - clicking the triggering button doesn't
  reliably produce an inspectable dialog here. Verify those actions via
  `Livewire::test()` or by calling the underlying Action directly (e.g. via
  `php artisan tinker`) instead of chasing the modal in this browser pane.

## A recurring bug class - check this on every new model

**Eloquent does not hydrate DB-level column defaults onto a freshly-created,
in-memory model instance** - only a re-fetch from the database does. If a
migration has `->default(...)` on a column and the model doesn't explicitly set
that field on create, `$model->thatColumn` reads `null` in memory even though the
actual DB row has the real default. This has caused a real `TypeError` or
`UnhandledMatchError` twice already:
- `User::canAccessPanel(): bool` on `is_active` (Module 2)
- `NginxConfigGeneratorService`'s `match ($website->status)` on
  `status`/`ssl_status`/`framework` (Module 3)

**Fix pattern**: add a `protected $attributes = [...]` array to the model matching
the migration's defaults exactly. Do this proactively for every new model with a
DB column default - don't wait for it to surface as a bug.

## Two PDO/MySQL gotchas (Module 4) - relevant to any future raw-SQL work

1. A `?` placeholder immediately after `@` (as in `` `user`@? ``) is not bound by
   the MySQL PDO driver - it's read as a user-defined-variable reference, not "at
   symbol then a bindable placeholder," and produces a syntax error with the `?`
   left as a literal character. Validate the value against a strict allowlist
   pattern and interpolate it directly instead of binding it in that position.
2. `CREATE USER ... IDENTIFIED BY ?` does not support a bound parameter at all -
   it isn't a preparable DML statement. Use `PDO::quote()` for safe manual
   interpolation of the value instead. Both were only found by running the real
   statements against this machine's actual MySQL 8.0.46 in
   `tests/Unit/Services/Databases/DatabaseManagerServiceTest.php` - a mocked
   `DB::statement()` call would never have caught either.

Also: Eloquent does **not** auto-cast pivot table attributes (e.g. a JSON column)
on the default anonymous pivot - `sync()`/`attach()` with an array value fails with
"Array to string conversion" unless the relationship uses a dedicated Pivot model
(`->using(YourPivot::class)`) with its own `casts()`. See
`App\Models\DatabaseUserDatabase`.

## Filament action closures: type-hint the real return type (Module 5)

A `->icon()`/`->color()`/`->label()` closure on a Filament `Action` is evaluated
**lazily**, only when Filament actually renders that action against a real
record - not when the resource class is loaded, not in any test that stops at
submitting a form. `WebsitesTable`'s "suspend" action shipped in Module 3 with
`->icon(fn (Website $record): string => ... Heroicon::OutlinedNoSymbol ...)` - a
`string` return type hint on a closure that returns an enum instance - and it sat
undetected through two more modules until Module 5's browser check loaded
`/admin/websites` with an actual row in it. **Every Filament resource needs at
least one test that renders its real list page with a real record present**, not
just Action-level or Policy-level tests - see
`tests/Feature/Websites/ListWebsitesPageTest.php` for the pattern now used to
catch this going forward.

## File Manager (Module 7): path validation, and three new lessons

`App\Services\FileManager\FileManagerService` is the only class that touches a
website's files, scoped to its `document_root`. It validates every relative path
**twice**: syntactically before resolution (rejects `..` segments, absolute/
drive-letter paths, null bytes), and again on the **resolved** `realpath()` after
resolution (catches symlink escapes a syntax-only check would miss). `unzip()`
additionally guards against zip-slip (re-validates every archive entry's target
path) and decompression bombs (rejects an archive whose uncompressed size exceeds
512 MB or whose uncompressed:compressed ratio exceeds 100:1, computed via
`ZipArchive::statIndex()` before extracting anything). See docs/Security.md.

Three bugs this module surfaced, worth remembering for future Livewire/Filament
work:
1. **A public Livewire property typed as a `Collection` of custom DTOs fails at
   render time** with "Property type not supported in Livewire" - Livewire's synth
   system only (de)hydrates specific types (arrays, Eloquent models/collections,
   primitives, registered synths), not arbitrary object graphs. Any derived,
   non-serializable value (like a live directory listing) should be a
   `#[Computed]` method (`use Livewire\Attributes\Computed;`), not a public
   property - it's recomputed fresh each render and never needs to survive
   dehydration/hydration. Call `unset($this->propertyName)` after a mutating
   action to clear its in-request memo if it was already read.
2. **`UploadedFile::move()` can fail against a real Livewire file upload even
   though it passes against `UploadedFile::fake()` in a plain unit test.**
   Livewire's `TemporaryUploadedFile` has its own temp-disk lifecycle that
   doesn't always tolerate a bare `move()` (rename-or-copy) call - it failed with
   an empty-message `FileException` only when driven through a real
   `Livewire::test()` upload. Fixed by reading the temp file's contents and
   writing them out instead (`File::put($target, File::get($file->getRealPath()))`),
   which works uniformly for both a genuine HTTP upload and a Livewire temporary
   one.
3. **A Blade component prop written as `heading="Upload &amp; create"` renders
   the literal text `Upload &amp; create` in the browser**, not `Upload & create`
   - Filament outputs the heading through `{{ }}`, which escapes the entity a
   second time. Write a literal `&` in Blade source, not a pre-escaped entity.
   This was only caught by an actual browser render (`get_page_text`), not by any
   Livewire feature test - a reminder that string-exact browser verification
   still catches things `assertSee()`-style feature tests can miss if the
   assertion doesn't happen to include the affected text.

Also: a typed class constant (`private const int NAME = ...`) is PHP 8.3+ syntax
and fails to parse on this machine's PHP 8.2.31 - caught immediately by `php -l`,
not by any test. Drop the type (`private const NAME = ...`) on this codebase.

## Terminal (Module 8): why it's one-shot commands, not a real PTY

A true interactive browser terminal (real keystroke-by-keystroke PTY, live shell
environment, tab completion) needs a long-lived process manager living outside
PHP-FPM/`artisan serve`'s normal request-response lifecycle - typically a Node
sidecar with `node-pty`, or a full WebSocket daemon bridging raw terminal I/O. That
infrastructure is out of proportion for what's buildable and genuinely testable in
this single-machine dev environment without a separate always-on process.

Instead, `App\Services\Terminal\TerminalCommandService::execute()` runs each
submitted line as its own fresh `Symfony\Process` (via
`Process::fromShellCommandline()`, deliberately - real shell semantics like `&&`,
pipes, and redirects are expected here, unlike `SystemCommandService`'s
whitelisted-array-args model), with `cd` specially intercepted to update the
session's stored `current_directory` in the database rather than spawning a
process - this is what makes navigating directories feel continuous across
one-shot commands. **Known, honest limitation**: no environment variable persists
between commands (`export FOO=bar` then a later command reading `$FOO` won't see
it) - this is a real capability gap versus genuine SSH, not a bug, and is
documented in the panel's own UI copy on the Terminal page.

`DangerousCommandGuard`'s regex list must **never** actually be executed for real
in a test, even under `confirmed: true` - `TerminalCommandServiceTest` proves the
confirmation-bypass behavior using `DROP DATABASE production` specifically because
"DROP" isn't a real executable on any OS's PATH (fails harmlessly with "command not
found"), never a genuinely destructive shell primitive like `rm -rf` or the fork
bomb pattern, which would actually damage the test machine (or a CI runner) if
actually run.

Two more Livewire/Alpine lessons this module surfaced:
1. The `#[Computed]`-not-a-public-property rule from Module 7 isn't limited to
   custom DTOs - a public property holding an **array of Eloquent models** hit the
   same serialization wall. `Terminal::$openSessions` had to become a
   `#[Computed]` method for the same reason `ManageFiles::entries()` did.
2. A `wire:ignore`'d element's `x-init` can fire **more than once for the same DOM
   node** - Livewire's morph hook and Alpine's own DOM observer can both end up
   processing the same freshly-inserted node, and Alpine's "already has `x-data`"
   guard doesn't prevent this because each processing pass can attach a distinct
   Alpine scope. This showed up as two full xterm.js instances mounted inside one
   terminal tab. A dataset-flag guard on the Blade side (`x-init="if
   (!$refs.pane.dataset.foo) {...}"`) did **not** fix it - the two invocations
   happened close enough together that both saw the flag unset. The fix has to
   live in the plain-JS function itself, keyed off the real DOM element (e.g.
   `if (el._mtpTerminal) return el._mtpTerminal;` at the top of
   `resources/js/terminal.js`'s `initMtpTerminal()`), not in Alpine/Blade-level
   state. Any future one-time-setup JS bridge on a `wire:ignore` element needs
   this same plain-DOM idempotency guard.

## Cloudflare (Module 9): the first module to deliberately break the real-infra testing rule

Every module since Module 4 tested against genuine local infrastructure - real
MySQL, real git, real composer, the real filesystem. Module 9 talks to
Cloudflare, a third-party SaaS: there is no real Cloudflare account, zone, or API
token anywhere in this dev environment, and unlike MySQL/git/composer, that's not
something installable locally. `App\Services\Cloudflare\CloudflareApiClient`
wraps Cloudflare's real REST API v4 via Laravel's `Http` facade; tests use
`Http::fake()` responses shaped exactly like Cloudflare's real, documented
envelope (`{success, errors, result}`). This proves the integration code is
correct (request shape, bearer auth header, response parsing, error surfacing)
but **cannot** prove a live account actually behaves this way. **Do one manual
smoke test against a real Cloudflare zone before relying on this in
production** - a disclosed, deliberate gap, not a silently-skipped one.

Two scope decisions worth knowing before extending this module:
1. DNS records are **not** persisted locally - `ManageCloudflare::dnsRecords()`
   fetches them live from Cloudflare on every render, the same "don't mirror a
   system that's already the source of truth" principle as Module 7's live
   filesystem reads. Only the zone connection itself (`cloudflare_zones`) and
   tunnel metadata (`cloudflare_tunnels`) are persisted.
2. Cloudflare Tunnels are account-scoped in Cloudflare's own API model (not
   zone/website-scoped), so they get their own account-level credential pair
   (`CLOUDFLARE_ACCOUNT_ID`/`CLOUDFLARE_ACCOUNT_API_TOKEN` in `config/services.php`)
   separate from each website's own zone token, and their own admin-only page
   (`App\Filament\Pages\CloudflareTunnels`) rather than living under a website.
   Creating a tunnel here only calls Cloudflare's API to create the tunnel
   *object* - it does not install or run the real `cloudflared` connector
   daemon on the server, so a tunnel created through this panel carries no
   traffic until that separate, unbuilt step happens. `cloudflare_tunnels.status`
   is never set to `Active` by this module, honestly reflecting that gap rather
   than faking a "connected" state.

## SSL (Module 10): hand-written ACME client, and a real Windows OpenSSL gotcha

`acmephp/core` (the standard PHP ACME client) is **not installable** in this
project - every release still pins `guzzlehttp/psr7 ^1.0`/`psr/http-message ^1.0`,
which conflicts with Laravel 12's Guzzle 7/psr-http-message 2.0 requirement.
`composer require acmephp/core --with-all-dependencies` would downgrade Guzzle
across the whole app - not acceptable. `App\Services\Ssl\AcmeClient` is a
from-scratch, narrowly-scoped RFC 8555 (ACME v2) client instead: JWS request
signing (RS256), nonce handling, order/authorization/challenge/finalize/download.
It only covers the happy path this panel needs - no account key rollover, no
external account binding, limited retry-on-badNonce handling. Don't reach for
a general ACME library here without re-checking the dependency conflict first;
it may have changed.

**This dev environment cannot complete a real Let's Encrypt issuance end to
end** - Let's Encrypt's servers validate domain control by connecting back to
a public IP/domain, which doesn't exist in this sandbox (same category of gap
as Module 9's Cloudflare account). Every ACME interaction is tested via
`Http::fake()` against real, documented ACME v2 response shapes instead. The
one thing worth doing differently from Module 9's Cloudflare tests: the JWS
signing itself is verified by actually checking the produced signature against
the account key's real public key (`openssl_verify()` in `AcmeClientTest`), not
just asserting a header exists - this is the highest-risk code in the module
(a subtly wrong JWS would fail silently against a real ACME server, never
against a permissive fake), so it gets checked for real even though the
network round-trip can't be.

**This machine's PHP build has no working default `openssl.cnf`** - every
`openssl_pkey_new()`/`openssl_csr_new()`/`openssl_csr_sign()` call fails
outright with `error:80000003:system library::No such process` unless an
explicit config path is passed in the options array (`['config' => $path]`).
Fixed by `config('mtp.openssl_config_path')`, pointing at
`C:/Program Files/Ampps/php82/extras/ssl/openssl.cnf` on this machine (set via
`.env`'s `MTP_OPENSSL_CONFIG_PATH` and mirrored in `phpunit.xml` for tests).
A real Linux server's PHP build normally has this working out of the box and
needs no override - don't "fix" this globally, it's a local dev-environment
quirk. Also: writing a Windows path with backslashes into `.env` breaks
dotenv parsing (`Encountered an unexpected escape sequence`) - always use
forward slashes in `.env` values, even for Windows paths.

## Backups (Module 13): built out of roadmap order, and a Windows path bug

Built ahead of Modules 11/12 (Cron Manager, Queue Manager) at the user's
explicit request - the roadmap order is a default, not a hard constraint, when
the user asks for something specific out of sequence. Cron/Queue Manager
remain next in the original order once resumed.

`WebsiteFileBackupService::restore()` originally compared a
slash-normalized `$targetPath` against a **non-normalized** `$destination`
(still containing Windows backslashes) in its zip-slip `str_starts_with()`
guard - the comparison always failed, so every zip entry was silently
skipped and `restore()` appeared to succeed while extracting nothing. Fixed
by normalizing `$destination` to forward slashes once, up front, in both
`backup()` and `restore()`. Caught immediately by
`WebsiteFileBackupServiceTest`'s real backup→corrupt→restore round trip,
which is exactly why that test doesn't just check "no exception thrown" but
asserts the actual file content came back.

`GitBackupService` reuses `App\Services\Deployments\GitDeploymentService`'s
(Module 5) real-git-process pattern, but talks to a completely separate bare
repository per website (`config('mtp.git_backups_path')`) - never the same
repo a website might be deployed from. `-c user.name=`/`-c user.email=` are
passed per-invocation rather than relying on system-wide git config, so this
works on a fresh server with no git identity configured yet.

## Cron Manager (Module 11): reused a transitive dependency instead of adding one

`dragonmantank/cron-expression` was already present via `laravel/framework`'s
own scheduler internals (`composer show dragonmantank/cron-expression`
confirms it) - no new Composer dependency needed for real cron expression
validation/next-run-date calculation. Check `composer show <package>` before
adding anything that Laravel's scheduler might already pull in transitively.

`SystemCrontabService::sync()` writes every *enabled* `CronJob` into the
real system crontab, but only inside a clearly-marked block
(`CrontabContentBuilder::BEGIN_MARKER`/`END_MARKER`) - anything a server
admin (or another tool) added to crontab by hand outside that block is
preserved verbatim on every sync, never clobbered. This is genuinely
untestable end-to-end on this Windows dev box (no `crontab` binary at all),
so `CrontabContentBuilder` (the pure string-generation half) is fully unit
tested for real, while `SystemCrontabServiceTest` only asserts the honest
failure path here - same split as `NginxConfigGeneratorService`/
`WebsiteProvisioningService` in Module 3.

## Queue Manager (Module 12): status honesty, and reusing the Action-returns-a-result pattern

`QueueWorker::status` has four states, not three: running/stopped/failed/**unknown**.
`unknown` is what gets set whenever a `supervisorctl` call itself couldn't be
reached (this Windows dev box has no such binary) - it is never silently
treated as "stopped" or "running." Don't collapse this back to three states;
the distinction between "confirmed stopped" and "couldn't confirm" matters.

`CreateQueueWorkerAction::handle()` returns `array{worker: QueueWorker, result:
SystemCommandResult}`, following the exact same shape as Module 3's
`CreateWebsiteAction` (`array{website: Website, provisioning: SystemCommandResult}`) -
whenever an Action both persists a DB row and attempts a real system-level
side effect that can fail independently of the DB write succeeding, return
both, and let the caller (a Filament page) decide how to surface a partial
failure rather than the Action silently swallowing or throwing on it.

## Logs (Module 14): the first server-facing module that needs no special binary

Every prior module reading real server state (crontab sync, supervisorctl,
terminal exec) has an honest-failure path on this Windows dev box because the
underlying binary doesn't exist here. `LogFileReaderService` doesn't have
that problem - `SplFileObject`-based file I/O works identically on any OS, so
it's the first module in this project fully exercised for real without a
disclosed dev-environment gap.

`SplFileObject::seek(PHP_INT_MAX)` then reading `->key()` is the trick used to
count total lines cheaply before seeking back to `total - maxLines` for
`tail()` - but a normally newline-terminated file (the common case) leaves one
phantom empty "line" after the last `\n`, since `SplFileObject` counts that
trailing empty string as its own line. Pop it off if it's empty, matching the
same convention `wc -l`/`tail` themselves use - otherwise `tail($path, 10)` on
a 500-line file returns 11 lines, off by one.

Filament's own `Page` class already declares a `content()` method (with a
completely different signature, returning a `Schema`) - naming a Livewire
computed property `content()` on a page subclass is a fatal "declaration not
compatible" error at parse time, not a runtime surprise. Named it
`logContent()` instead. Worth checking `Page`'s own method list before naming
a computed property/method on any Filament page subclass.

## Monitoring & Alerts (Module 15): building on Module 2 instead of duplicating it

Before writing any code, checked what Module 2 (Dashboard) already built -
`SystemMetricSnapshot` (a row captured every minute), `MetricsTrendChart`
(a 60-snapshot CPU/Memory line chart), and `app:capture-system-metrics`
already existed. Module 15 extended these instead of introducing a parallel
metrics table: added a third Disk % dataset to the existing chart, and hung
alert evaluation + snapshot pruning off the existing scheduled command,
rather than inventing a second command or a second snapshot table. Always
check for a prior module's partial overlap before designing a new one's
schema - the Roadmap module boundaries are a planning convenience, not a
guarantee that no code exists yet.

`system_metric_snapshots` had no pruning at all before this module - a
once-a-minute insert with nothing ever deleting old rows is an unbounded
table by construction. Any "capture on a schedule" feature needs a retention
policy from day one, not as an afterthought once the table is already large;
Module 13's backup retention count was the precedent followed here
(`config('mtp.metrics_retention_days')`, pruned in the same command that
does the capturing).

Filament's `discoverWidgets()` scans `app/Filament/Widgets` and auto-attaches
every class in it to the default `Filament\Pages\Dashboard` - a page-specific
chart widget dropped in that directory would silently appear on the main
Dashboard too, not just the page it was built for. Avoided the question
entirely by not introducing a new ChartWidget class for the Monitoring page:
the bandwidth table and process list are computed directly in the page's own
Livewire class instead. Worth remembering before adding any new widget meant
for a page other than the Dashboard.

## Notifications (Module 16): Mail::fake() doesn't record Mail::raw() at all

`Illuminate\Support\Testing\Fakes\MailFake::raw()` is a literal no-op - it
does not build or record anything, because `Mail::raw()` never constructs an
`Illuminate\Mail\Mailable` instance in the first place (it goes through a
completely different code path than `Mail::to()->send($mailable)`). A test
asserting `Mail::assertSent(...)` after a `Mail::raw()` call will silently
always fail with zero recorded mail, no matter what the closure checks -
there is no exception, just an assertion failure that looks like the wrong
recipient rather than "nothing was ever recorded." Switched
`NotificationDispatchService`'s email channel to build a real
`App\Mail\PlainNotificationMail extends Mailable` and send it via
`Mail::to($recipient)->send($mailable)` instead - genuinely testable via
`Mail::fake()`/`Mail::assertSent()`.

Relatedly: `Mail::assertSent(fn ($mailable) => ...)` throws `RuntimeException:
The first parameter of the given Closure is missing a type hint` unless the
closure's parameter is typed - Laravel uses reflection on the closure to
infer which mailable class to filter for, so `fn ($mailable) => ...` always
fails this way; it must be `fn (PlainNotificationMail $mailable) => ...`.

Conditionally-visible schema fields (`->visible(fn (Get $get) => ...)` keyed
off a `->live()` Select) proved fragile to drive through Filament's table
action testing helpers (`callTableAction`/`setTableActionData`) - state set
directly via the test harness doesn't necessarily replay the same
visibility-then-dehydrate sequence a real interactive browser session does,
so a hidden-at-fill-time field's value can silently vanish from the
dehydrated `$data` array even though it was passed in. Rather than fighting
the test harness, simplified the actual form: all channel-specific config
fields (bot token, chat ID, webhook URL) are always visible with a label
prefix naming which channel type they belong to, instead of conditionally
shown/hidden. Simpler, more robust to test, and a perfectly reasonable UX
choice for an internal admin tool - not every form needs live conditional
visibility.

`User::role([...])` (Spatie's role-scoping query) throws `RoleDoesNotExist`
if a named role doesn't exist in the database **at all**, not just returning
an empty result - any code path that calls it (here,
`AlertEvaluatorService` notifying admins on a new alert) requires
`PermissionSeeder`/`RoleSeeder` to have run first, even in a test that
creates zero users. Forgetting this seed step doesn't fail quietly.

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
