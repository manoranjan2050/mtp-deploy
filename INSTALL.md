# Installing MTP Deploy

This guide covers installing MTP Deploy on a fresh Ubuntu 22.04/24.04 (or Debian
equivalent) server. There are two paths:

- **[Quick start](#quick-start)** — run `install.sh`, done in a few minutes.
- **[Manual step-by-step](#manual-step-by-step)** — if you want to see/control every
  step, or you're on an OS `install.sh` doesn't support.

## Requirements

- A fresh Ubuntu 22.04/24.04 (or Debian 12) server, root or sudo access.
- A domain name pointed at the server's IP address (recommended, not required —
  the panel works over the server's bare IP too, just without a real SSL cert
  until you point a domain at it and use Module 10 in the panel).
- At least 1 GB RAM, 1 vCPU, 10 GB disk as a practical minimum.

## Quick start

```bash
git clone https://github.com/manoranjan2050/mtp-deploy.git
cd mtp-deploy
sudo ./install.sh
```

That's it. The script is idempotent — safe to re-run if it fails partway through
(each step checks whether it already ran before repeating itself).

By default it installs everything under the domain `_` (nginx's catch-all —
reachable at `http://<server-ip>/admin`). To install for a real domain from the
start:

```bash
sudo MTP_DOMAIN=deploy.example.com ./install.sh
```

Other variables you can override (see the top of `install.sh` for the full list):

| Variable | Default | Purpose |
|---|---|---|
| `MTP_DOMAIN` | `_` | nginx `server_name` for the panel itself |
| `MTP_DB_NAME` | `mtpdeploy` | App's own database name |
| `MTP_DB_USER` | `mtpdeploy` | App's own database user |
| `MTP_DB_PASSWORD` | *(random, generated)* | App's own database password |
| `MTP_PHP_VERSION` | `8.4` | PHP version to install (via `ppa:ondrej/php`) |
| `MTP_INSTALL_PHPMYADMIN` | `yes` | Set to `no` to skip installing phpMyAdmin |
| `MTP_SYSTEM_USER` | `www-data` | Unix user the app/queue worker runs as |

### What the script installs

- PHP 8.4 + extensions (mbstring, xml, curl, zip, gd, mysql, redis, bcmath, intl,
  opcache, sqlite3)
- Composer
- Node.js LTS + npm (for building the Filament/Tailwind/Vite front-end assets)
- MariaDB (creates the app's own database + a dedicated, non-root database user)
- Redis
- nginx (writes a vhost for the panel itself, reloads nginx)
- Supervisor (a `queue:work` program so background jobs actually run)
- phpMyAdmin (optional, reachable at `/phpmyadmin` under the panel's own vhost)
- A cron entry running Laravel's scheduler every minute (this is what actually
  captures dashboard metrics, checks SSL renewals, etc. — see docs/Roadmap.md)

### After it finishes

1. Visit `http://<your-domain-or-ip>/admin` and register the **first** account —
   it is automatically granted `super-admin` (see `app/Listeners/Auth/AssignSuperAdminRoleOnFirstRegistration.php`).
   Registration is only reachable while zero users exist; after that it redirects
   to the login page.
2. **Set up 2FA immediately** for that account — Filament's built-in Multi-Factor
   Authentication is already wired in (Profile → enable App/TOTP) and is
   *required* for `admin`/`super-admin` roles (see `AdminPanelProvider`).
3. Copy the database password the script printed somewhere safe — it's also in
   `.env`, but that file is only readable as root/`www-data`.
4. Add a real website (Websites → New website), point its domain's DNS `A`
   record at this server, then issue it a real SSL certificate via the
   website's SSL page (Module 10) — set `MTP_ACME_DIRECTORY_URL` in `.env` to
   Let's Encrypt's **production** directory first
   (`https://acme-v02.api.letsencrypt.org/directory`); it defaults to the
   **staging** directory so a misconfigured first attempt never burns your
   real-world Let's Encrypt rate limit.
5. If you want database backups/tunnels/Cloudflare integration, connect the
   relevant credentials from inside the panel (Cloudflare zone tokens, backup
   destinations) — none of that lives in `install.sh`, it's all configured
   per-website/per-server from the UI once the panel itself is running.

### Troubleshooting

- **`nginx -t` fails after install** — check `/etc/nginx/sites-available/mtp-deploy.conf`;
  the most common cause is another vhost already claiming port 80/443 for the
  same `server_name`.
- **500 error on first visit** — check `storage/logs/laravel.log`; usually a
  missing `APP_KEY` (re-run `php artisan key:generate --force` from the app
  directory) or a database connection issue (check `.env`'s `DB_*` values
  against what MariaDB actually has: `mysql -u root -e "SELECT User,Host FROM mysql.user;"`).
- **Queue jobs never run** — `sudo supervisorctl status` should show
  `mtp-deploy-worker` as `RUNNING`; if not, `sudo supervisorctl reread && sudo supervisorctl update`.
- **Scheduled tasks (metrics, SSL renewal) never run** — confirm the cron entry
  exists: `cat /etc/cron.d/mtp-deploy`, and that cron itself is running:
  `systemctl status cron`.

## Manual step-by-step

If you'd rather do this by hand (or `install.sh` doesn't support your OS), here's
every step it automates:

```bash
# 1. System packages
sudo apt-get update
sudo apt-get install -y software-properties-common ca-certificates curl gnupg git unzip

# 2. PHP 8.4
sudo add-apt-repository -y ppa:ondrej/php
sudo apt-get update
sudo apt-get install -y php8.4 php8.4-fpm php8.4-cli php8.4-mysql php8.4-redis \
    php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath \
    php8.4-intl php8.4-opcache php8.4-sqlite3

# 3. Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# 4. Node.js
curl -fsSL https://deb.nodesource.com/setup_lts.x | sudo bash -
sudo apt-get install -y nodejs

# 5. MariaDB, Redis, nginx, Supervisor
sudo apt-get install -y mariadb-server mariadb-client redis-server nginx supervisor
sudo systemctl enable --now mariadb redis-server nginx supervisor

# 6. Database + user
sudo mysql -u root -e "
  CREATE DATABASE mtpdeploy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'mtpdeploy'@'127.0.0.1' IDENTIFIED BY 'CHANGE-ME';
  GRANT ALL PRIVILEGES ON mtpdeploy.* TO 'mtpdeploy'@'127.0.0.1';
  FLUSH PRIVILEGES;
"

# 7. App setup
git clone https://github.com/manoranjan2050/mtp-deploy.git
cd mtp-deploy
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
# edit .env: DB_DATABASE, DB_USERNAME, DB_PASSWORD, APP_URL, APP_ENV=production, APP_DEBUG=false
npm install && npm run build
php artisan migrate --force --seed
php artisan storage:link
sudo chown -R www-data:www-data .
sudo chmod -R 775 storage bootstrap/cache

# 8. nginx vhost — see install.sh's NGINX heredoc for the exact template,
#    write it to /etc/nginx/sites-available/mtp-deploy.conf, symlink into
#    sites-enabled, then:
sudo nginx -t && sudo systemctl reload nginx

# 9. Supervisor queue worker — see install.sh's SUPERVISOR heredoc, write to
#    /etc/supervisor/conf.d/mtp-deploy-worker.conf, then:
sudo supervisorctl reread && sudo supervisorctl update

# 10. Scheduler cron entry
echo "* * * * * www-data cd $(pwd) && php artisan schedule:run >> /dev/null 2>&1" \
  | sudo tee /etc/cron.d/mtp-deploy

# 11. Production caches
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## Security notes

- Change `MTP_DB_PASSWORD` from the auto-generated value if you have any
  reason to believe `/dev/urandom` was compromised on this box (extremely
  unlikely, but the option is there).
- `APP_DEBUG` is forced to `false` and `APP_ENV` to `production` by the
  installer — never flip these back on a real server, `APP_DEBUG=true` leaks
  stack traces (including `.env` values) to any visitor who triggers an error.
- 2FA is *required* for `admin`/`super-admin` roles the moment you enable it in
  your profile — there is no way to disable that requirement for those roles
  short of editing `AdminPanelProvider`'s `multiFactorAuthentication()` call.
- Terminal (Module 8) and Cron/Queue management give whoever holds `admin`/
  `super-admin` full shell-equivalent access to this server by design — treat
  those accounts with the same care as root SSH access.
