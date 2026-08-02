# Vision — MTP Deploy

## Tagline
**Modern Self Hosted Deployment Platform**

## Mission
MTP Deploy lets a developer take a bare Linux server and turn it into a fully managed
Laravel/PHP hosting environment entirely from a browser — no manual nginx config
editing, no manual database creation, no manual SSL certificate juggling.

It occupies the same space as Laravel Forge, Ploi, RunCloud, CloudPanel, and aaPanel,
but is:

- **Self-hosted** — the control panel itself runs on infrastructure the user owns.
  There is no vendor lock-in and no monthly SaaS fee to a third party.
- **Laravel-native** — first-class support for the Laravel deployment lifecycle
  (composer, artisan, queues, scheduler) rather than being a generic PHP panel with
  Laravel bolted on.
- **Single binary-ish footprint** — one Laravel 12 + Filament v4 application manages
  itself and (eventually) any number of remote servers over SSH.

## Target Users
- Laravel / PHP developers who want Forge-like ergonomics without a subscription
- Agencies hosting client sites who need multi-tenant server management
- Freelancers running a handful of client projects on a single VPS
- Companies wanting an internal, auditable deployment platform
- Home-lab users self-hosting personal projects

## Product Principles
1. **Never lie about server state.** Every status shown in the UI must be read live
   (or from a short-lived cache) from the actual system — no fabricated defaults.
2. **Reversible by default.** Destructive actions (delete site, drop database, revoke
   SSL) require explicit confirmation and, where feasible, a recovery path (backups,
   soft-delete, trash).
3. **Least privilege.** The web app never runs arbitrary root shell. Every privileged
   system operation goes through a narrow, whitelisted, auditable command layer.
4. **One module at a time.** The system is built and shipped incrementally per the
   [Roadmap](Roadmap.md). Each module is usable and tested before the next begins.
5. **Boring technology, solid architecture.** SOLID principles, explicit layers
   (Repository / Service / Action / Job / Event / Listener / Policy / DTO / Enum),
   and full test coverage over cleverness.

## Non-Goals (for now)
- Being a generic cPanel replacement for non-PHP stacks (Node/Python/Go support may
  come later via Module 19 Docker, but is not the initial focus)
- Managing bare-metal provisioning (buying/provisioning VPS instances) — MTP Deploy
  manages servers you already have SSH access to
- Windows server targets — Linux (Ubuntu/Debian) only for managed servers

## Success Criteria
A developer can, in one sitting:
1. Register an account, enable 2FA, and log into the panel.
2. Add a Laravel site, get an nginx vhost + SSL certificate provisioned automatically.
3. Connect a GitHub repo, push to `main`, and see the site auto-deploy via webhook.
4. See CPU/RAM/disk/queue/cron health for that server on the Dashboard.
5. Restore a database from a scheduled backup without touching a terminal.
