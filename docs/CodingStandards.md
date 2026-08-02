# Coding Standards — MTP Deploy

## Style
- PSR-12, enforced via Laravel Pint (`vendor/bin/pint`) — run before every commit.
- Strict types: every PHP file starts with `declare(strict_types=1);`.
- Constructor property promotion + `readonly` for DTOs and value objects.
- Native enums (`enum X: string`) for every fixed value set — never string/int
  "magic values" compared with `===` in business logic.

## Naming
- Models: singular (`Website`, not `Websites`).
- Actions: verb-first, one responsibility (`CreateWebsiteAction`, not
  `WebsiteService::create()` doing five things).
- Services: named after the capability, not the model (`SslService`, not
  `CertificateService` if it also handles renewal orchestration for multiple cert
  types).
- Events: past tense (`WebsiteCreated`), Listeners: imperative
  (`ProvisionDefaultSslCertificate`).
- Form Requests: `{Verb}{Model}Request` (`CreateWebsiteRequest`).
- DTOs: `{Noun}Data` (`RegisterUserData`).
- Enums: singular noun (`UserStatus`, not `UserStatuses`).

## Laravel 12 specifics (pitfalls already hit in other projects — see memory)
- **No `EventServiceProvider` auto-discovery.** Laravel 12's skeleton does not
  auto-register listeners the way Laravel 9/10 did. Register every
  `Event::listen()` explicitly in `AppServiceProvider::boot()` (or a dedicated
  `EventServiceProvider` if the list grows past ~10) — do **not** duplicate a
  manual `Event::listen()` call from inside a listener class itself, which
  double-fires it.
- Full-page Livewire routes need the `#[Layout('...')]` attribute on the component
  class; wrapping the Blade view in `<x-layout>` does not apply the layout for
  route-bound Volt/Livewire components.
- Filament v4 panel/resource generation: run `php artisan make:filament-resource`
  and commit the generated resource before hand-customizing — never hand-write a
  resource from scratch when the generator covers 90% of it.

## Testing
- Every Action gets a Feature test exercising it through the real HTTP/Livewire
  surface (not just a unit test calling the Action directly) — the point is to
  catch policy/middleware regressions too.
- Every Policy gets an explicit "denies" test, not just an "allows" test.
- `php artisan test` (Pest or PHPUnit — this project uses PHPUnit, Laravel 12's
  default) must be green before a module is marked complete in
  [Roadmap.md](Roadmap.md) / [TODO.md](../TODO.md).
- Minimum bar per module: happy path + one authorization-boundary test per
  mutating endpoint. Not aiming for 100% line coverage — aiming for "every place a
  bug would be a security or data-loss incident is tested."

## Git
- Conventional-ish commit messages (`feat:`, `fix:`, `docs:`, `refactor:`) — matches
  this repo's own history convention already seen in sibling projects.
- One module's work lands as a cohesive set of commits before moving to the next
  module; do not interleave two modules' changes in the same commit.

## Dependencies
- Prefer official Laravel/Filament first-party packages and well-maintained Spatie
  packages over rolling custom implementations of solved problems (permissions,
  activity logging, media handling).
- Every new Composer/NPM dependency is a deliberate choice — record *why* in the
  PR/commit message if it's not obvious from the package name.

## Documentation
- Every module, when complete, gets its [Database.md](Database.md) section
  finalized (sketched sections become authoritative) and its
  [Features.md](Features.md) checklist ticked.
- Code comments only where the *why* isn't obvious from the code itself (a
  workaround, a non-obvious invariant, a security-relevant constraint) — never
  comments that restate what the code does.
