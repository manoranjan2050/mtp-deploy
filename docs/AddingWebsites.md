# Adding a New Domain or Subdomain

Repeatable checklist for publishing any new site (root domain or subdomain)
through this MTP Deploy install, using the Cloudflare Tunnel already set up
(`mtpcode-tunnel`).

## 1. Add it as a website in the panel

`Websites → New website`:

- **Domain**: the exact hostname (e.g. `blog.mtpcode.in`, or a brand-new root
  domain like `example.com`)
- **PHP version**, **framework** (Laravel / static / plain PHP)
- Leave **aliases** empty unless you genuinely want extra hostnames served by
  the *same* vhost — don't put the domain itself in there (a domain
  duplicated into its own aliases list used to produce a harmless-but-noisy
  nginx "conflicting server name" warning; fixed, but still not needed).

Saving it automatically:

- Creates the nginx vhost (`/etc/nginx/sites-available/<domain>.conf`)
- Creates the document root (`/var/www/<domain>/`, or `/var/www/<domain>/public`
  for Laravel)
- Reloads nginx

If a save ever reports "provisioning failed", re-open the website and click
**Save** again — the vhost + document root creation is idempotent and will
retry on every save.

## 2. Route it through the tunnel

No new tunnel needed — one tunnel carries every hostname. Cloudflare Zero
Trust dashboard → **Networks → Tunnels → mtpcode-tunnel → Published
application routes → Add a route**:

- **Domain**: pick the correct zone from the dropdown (e.g. `mtpcode.in`) —
  double check this if you manage more than one domain in the same
  Cloudflare account, a wrong selection here is an easy typo to make
- **Subdomain**: the part before the domain (e.g. `blog`) — leave blank for
  a bare root domain
- **Service Type**: `HTTP`
- **URL**: `localhost:80`

Cloudflare auto-creates the DNS record. If you get **"A DNS record with this
name already exists"**, an old record (often a stale `A`/`CNAME` from before
the site used the tunnel) is blocking it — go to the domain's zone →
**DNS → Records**, delete the conflicting record with that name, then retry
adding the route.

Since traffic arrives through the tunnel, Cloudflare's edge already serves
HTTPS to visitors — you generally don't need a separate Let's Encrypt
certificate via Module 10 for tunnel-routed sites (that module matters more
for domains exposed by port-forwarding directly to the server instead).

## 3. Get your files onto the server

Two options:

### A. Upload a zip through the panel's File Manager

`Websites → <site> → Files` → upload the zip → select it → **Extract**.
Works for files up to 200MB (raised from PHP/nginx's tiny stock defaults —
see `install.sh`). No FTP/WinSCP needed for this path.

### B. SFTP/WinSCP, or `scp`/`rsync` from a terminal

Still works exactly as normal — the document root is a plain directory on
disk (`/var/www/<domain>/`), nothing panel-specific about how files get
there.

### If it's a full Laravel app (not just static files)

After the files are in place, you still need, once:

1. A dedicated MySQL database + user for it — either via `Databases` in the
   panel (Module 4), or manually if you're comfortable with SQL.
2. A real `.env` — either your app's own installer wizard if it has one
   (like Helishield's `public/installer/`), or by hand:
   `APP_KEY`, `DB_*`, `APP_URL` (use `https://` — see the tunnel note above).
3. `php artisan migrate --seed`, `php artisan storage:link`.
4. Correct ownership: everything under `/var/www/<domain>/` needs to be
   `www-data:www-data` (the panel already sets this when it provisions the
   directory; re-run `sudo chown -R www-data:www-data /var/www/<domain>`
   if you uploaded files as your own user via SFTP instead of the panel).

## Gotchas already hit and fixed once

- **`PHP_BINARY` inside a web installer resolves to `php-fpm`, not the CLI
  `php` binary** — if an app's own installer shells out to run `artisan`
  commands, make sure it detects `PHP_SAPI === 'cli'` before trusting
  `PHP_BINARY`, otherwise migrations fail with a dump of php-fpm's own
  usage text instead of running.
- **Laravel factories always evaluate their base `definition()`** (which
  may call the `fake()` helper) even when every field gets overridden.
  `fake()` only exists if `fakerphp/faker` is installed, which is correctly
  *absent* from a `composer install --no-dev` production build — so a
  seeder using `User::factory()->create([...])` with fixed values still
  needs Faker and will crash in production. Use `User::forceCreate([...])`
  directly for seeded rows that aren't meant to be random.
- **MariaDB's TCP loopback connections can authenticate as `localhost`
  even when connecting to `127.0.0.1`** — always create both
  `'user'@'127.0.0.1'` and `'user'@'localhost'` for any database user,
  not just one.
