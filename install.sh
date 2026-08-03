#!/usr/bin/env bash
#
# MTP Deploy — one-shot installer for a fresh Ubuntu/Debian server.
#
# Usage:
#   git clone https://github.com/manoranjan2050/mtp-deploy.git
#   cd mtp-deploy
#   sudo ./install.sh
#
# What it does (see INSTALL.md for the full step-by-step explanation):
#   - Installs PHP 8.4 + required extensions, Composer, Node.js/npm
#   - Installs MariaDB, Redis, nginx, Supervisor, phpMyAdmin
#   - Creates a dedicated app database + database user
#   - Installs Composer/npm dependencies and builds front-end assets
#   - Configures .env, runs migrations + seeders
#   - Writes an nginx vhost for the panel itself and reloads nginx
#   - Writes a Supervisor program for the queue worker
#   - Installs a cron entry for Laravel's scheduler
#
# Safe to re-run: every step checks whether it already did its job before
# doing it again.

set -euo pipefail

# A `generator | head -c N` pipeline is not safe under `set -o pipefail`: once
# `head` has read its N bytes it closes the pipe, the still-writing generator
# gets SIGPIPE, and the pipeline's (nonzero, SIGPIPE) exit status - captured
# by the surrounding `$(...)` - trips `set -e` and silently kills the whole
# script right there, with no error message. Reading a bounded amount of
# input first, then truncating with bash's own substring expansion (no pipe,
# no subprocess), avoids the problem entirely.
random_string() {
    local raw
    raw="$(head -c 200 /dev/urandom | tr -dc 'A-Za-z0-9')"
    echo "${raw:0:24}"
}

# ---------------------------------------------------------------------------
# Configuration (override via environment variables before running, e.g.
#   MTP_DOMAIN=deploy.example.com sudo -E ./install.sh
# ---------------------------------------------------------------------------
MTP_DOMAIN="${MTP_DOMAIN:-_}"                      # nginx server_name; "_" = catch-all on this machine's IP
MTP_APP_DIR="${MTP_APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)}"
MTP_DB_NAME="${MTP_DB_NAME:-mtpdeploy}"
MTP_DB_USER="${MTP_DB_USER:-mtpdeploy}"
MTP_DB_PASSWORD="${MTP_DB_PASSWORD:-$(random_string)}"
MTP_DB_ADMIN_USER="${MTP_DB_ADMIN_USER:-mtpdeploy_admin}"
MTP_DB_ADMIN_PASSWORD="${MTP_DB_ADMIN_PASSWORD:-$(random_string)}"
MTP_PHP_VERSION="${MTP_PHP_VERSION:-8.4}"
MTP_INSTALL_PHPMYADMIN="${MTP_INSTALL_PHPMYADMIN:-yes}"
MTP_SYSTEM_USER="${MTP_SYSTEM_USER:-www-data}"

log()  { echo -e "\033[1;32m==>\033[0m $*"; }
warn() { echo -e "\033[1;33m!!\033[0m $*"; }
die()  { echo -e "\033[1;31mERROR:\033[0m $*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "Run this script as root (or with sudo)."
[[ -f "${MTP_APP_DIR}/artisan" ]] || die "Could not find artisan in ${MTP_APP_DIR} - run this from inside the cloned repo."
command -v apt-get >/dev/null 2>&1 || die "This installer supports Ubuntu/Debian (apt-get) only."

export DEBIAN_FRONTEND=noninteractive

# ---------------------------------------------------------------------------
log "Updating package lists"
# ---------------------------------------------------------------------------
apt-get update -y
apt-get install -y software-properties-common ca-certificates curl gnupg lsb-release apt-transport-https unzip git

# ---------------------------------------------------------------------------
log "Installing PHP ${MTP_PHP_VERSION} and required extensions"
# ---------------------------------------------------------------------------
if ! php -v 2>/dev/null | grep -q "PHP ${MTP_PHP_VERSION}"; then
    add-apt-repository -y ppa:ondrej/php
    apt-get update -y
fi

apt-get install -y \
    "php${MTP_PHP_VERSION}" "php${MTP_PHP_VERSION}-fpm" "php${MTP_PHP_VERSION}-cli" \
    "php${MTP_PHP_VERSION}-mysql" "php${MTP_PHP_VERSION}-redis" "php${MTP_PHP_VERSION}-mbstring" \
    "php${MTP_PHP_VERSION}-xml" "php${MTP_PHP_VERSION}-curl" "php${MTP_PHP_VERSION}-zip" \
    "php${MTP_PHP_VERSION}-gd" "php${MTP_PHP_VERSION}-bcmath" "php${MTP_PHP_VERSION}-intl" \
    "php${MTP_PHP_VERSION}-opcache" "php${MTP_PHP_VERSION}-sqlite3" "php${MTP_PHP_VERSION}-common"

update-alternatives --set php "/usr/bin/php${MTP_PHP_VERSION}" 2>/dev/null || true

# The php-fpm package's own postinst usually enables it already, but that's
# an implementation detail of the package, not something this script should
# rely on silently - make it explicit so the panel actually survives a reboot.
systemctl enable --now "php${MTP_PHP_VERSION}-fpm"

# ---------------------------------------------------------------------------
log "Installing Composer"
# ---------------------------------------------------------------------------
if ! command -v composer >/dev/null 2>&1; then
    curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
fi

# ---------------------------------------------------------------------------
log "Installing Node.js LTS + npm"
# ---------------------------------------------------------------------------
if ! command -v node >/dev/null 2>&1 || [[ "$(node -v | cut -d. -f1 | tr -d v)" -lt 20 ]]; then
    curl -fsSL https://deb.nodesource.com/setup_lts.x | bash -
    apt-get install -y nodejs
fi

# ---------------------------------------------------------------------------
log "Installing MariaDB server"
# ---------------------------------------------------------------------------
if ! command -v mariadb >/dev/null 2>&1 && ! command -v mysql >/dev/null 2>&1; then
    apt-get install -y mariadb-server mariadb-client
    systemctl enable --now mariadb
fi

log "Creating the application database and user (if they don't already exist)"
# `mysql -u root` here relies on MariaDB's default unix_socket auth for the
# real root account, which only works for a local shell running as the root
# OS user (exactly what this script is, under sudo) - it does NOT work over
# TCP with a password, which is why the app itself never connects as root.
mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`${MTP_DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${MTP_DB_USER}'@'127.0.0.1' IDENTIFIED BY '${MTP_DB_PASSWORD}';
CREATE USER IF NOT EXISTS '${MTP_DB_USER}'@'localhost' IDENTIFIED BY '${MTP_DB_PASSWORD}';
ALTER USER '${MTP_DB_USER}'@'127.0.0.1' IDENTIFIED BY '${MTP_DB_PASSWORD}';
ALTER USER '${MTP_DB_USER}'@'localhost' IDENTIFIED BY '${MTP_DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${MTP_DB_NAME}\`.* TO '${MTP_DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON \`${MTP_DB_NAME}\`.* TO '${MTP_DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

log "Creating a dedicated admin MySQL user for Module 4's Database Manager"
# Module 4 provisions/drops *other* databases and users for managed websites -
# that genuinely needs privileged MySQL access, deliberately separate from
# the app's own DB_* connection above (which is scoped to only its own
# database). A dedicated, rotatable admin user - not the real MySQL root
# account - keeps that privileged credential out of the app's .env.
mysql -u root <<SQL
CREATE USER IF NOT EXISTS '${MTP_DB_ADMIN_USER}'@'127.0.0.1' IDENTIFIED BY '${MTP_DB_ADMIN_PASSWORD}';
CREATE USER IF NOT EXISTS '${MTP_DB_ADMIN_USER}'@'localhost' IDENTIFIED BY '${MTP_DB_ADMIN_PASSWORD}';
ALTER USER '${MTP_DB_ADMIN_USER}'@'127.0.0.1' IDENTIFIED BY '${MTP_DB_ADMIN_PASSWORD}';
ALTER USER '${MTP_DB_ADMIN_USER}'@'localhost' IDENTIFIED BY '${MTP_DB_ADMIN_PASSWORD}';
GRANT ALL PRIVILEGES ON *.* TO '${MTP_DB_ADMIN_USER}'@'127.0.0.1' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO '${MTP_DB_ADMIN_USER}'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL

# ---------------------------------------------------------------------------
log "Installing Redis"
# ---------------------------------------------------------------------------
if ! command -v redis-server >/dev/null 2>&1; then
    apt-get install -y redis-server
    systemctl enable --now redis-server
fi

# ---------------------------------------------------------------------------
log "Installing nginx"
# ---------------------------------------------------------------------------
if ! command -v nginx >/dev/null 2>&1; then
    apt-get install -y nginx
    systemctl enable --now nginx
fi

# ---------------------------------------------------------------------------
log "Installing Supervisor"
# ---------------------------------------------------------------------------
if ! command -v supervisorctl >/dev/null 2>&1; then
    apt-get install -y supervisor
    systemctl enable --now supervisor
fi

# ---------------------------------------------------------------------------
if [[ "${MTP_INSTALL_PHPMYADMIN}" == "yes" ]]; then
    log "Installing phpMyAdmin (accessible at /phpmyadmin behind the panel's nginx vhost)"
    if [[ ! -d /usr/share/phpmyadmin ]]; then
        PMA_PASSWORD="$(random_string)"
        debconf-set-selections <<< "phpmyadmin phpmyadmin/dbconfig-install boolean true"
        debconf-set-selections <<< "phpmyadmin phpmyadmin/app-password-confirm password ${PMA_PASSWORD}"
        debconf-set-selections <<< "phpmyadmin phpmyadmin/mysql/admin-pass password"
        debconf-set-selections <<< "phpmyadmin phpmyadmin/mysql/app-pass password ${PMA_PASSWORD}"
        debconf-set-selections <<< "phpmyadmin phpmyadmin/reconfigure-webserver multiselect"
        apt-get install -y phpmyadmin
    fi
fi

# ---------------------------------------------------------------------------
log "Installing Composer dependencies"
# ---------------------------------------------------------------------------
cd "${MTP_APP_DIR}"
sudo -u "${MTP_SYSTEM_USER}" composer install --no-dev --optimize-autoloader --no-interaction || \
    composer install --no-dev --optimize-autoloader --no-interaction

# ---------------------------------------------------------------------------
log "Configuring .env"
# ---------------------------------------------------------------------------
if [[ ! -f .env ]]; then
    cp .env.example .env
fi

php artisan key:generate --force

# `s@^#?[[:space:]]*KEY=.*@KEY=value@` matches the line whether or not it's
# commented out (Laravel's stock .env.example ships DB_HOST/PORT/DATABASE/
# USERNAME/PASSWORD commented out by default) and replaces the whole line,
# uncommented, with the real value - a plain `s#^KEY=.*#...#` silently does
# nothing on a commented `# KEY=...` line, which is exactly what happened
# here originally: the sed "succeeded" (no error) but never touched anything,
# so migrate connected with Laravel's fallback config defaults (root/laravel)
# instead of the real app credentials just created above.
sed -i -E \
    -e "s@^#?[[:space:]]*DB_CONNECTION=.*@DB_CONNECTION=mysql@" \
    -e "s@^#?[[:space:]]*DB_HOST=.*@DB_HOST=127.0.0.1@" \
    -e "s@^#?[[:space:]]*DB_PORT=.*@DB_PORT=3306@" \
    -e "s@^#?[[:space:]]*DB_DATABASE=.*@DB_DATABASE=${MTP_DB_NAME}@" \
    -e "s@^#?[[:space:]]*DB_USERNAME=.*@DB_USERNAME=${MTP_DB_USER}@" \
    -e "s@^#?[[:space:]]*DB_PASSWORD=.*@DB_PASSWORD=${MTP_DB_PASSWORD}@" \
    -e "s@^#?[[:space:]]*DB_ADMIN_HOST=.*@DB_ADMIN_HOST=127.0.0.1@" \
    -e "s@^#?[[:space:]]*DB_ADMIN_PORT=.*@DB_ADMIN_PORT=3306@" \
    -e "s@^#?[[:space:]]*DB_ADMIN_USERNAME=.*@DB_ADMIN_USERNAME=${MTP_DB_ADMIN_USER}@" \
    -e "s@^#?[[:space:]]*DB_ADMIN_PASSWORD=.*@DB_ADMIN_PASSWORD=${MTP_DB_ADMIN_PASSWORD}@" \
    -e "s@^APP_ENV=.*@APP_ENV=production@" \
    -e "s@^APP_DEBUG=.*@APP_DEBUG=false@" \
    .env

# The two DB_ADMIN_* lines might not exist at all in .env.example yet on an
# older checkout - append them if the sed above found nothing to replace.
grep -q "^DB_ADMIN_HOST=" .env || echo "DB_ADMIN_HOST=127.0.0.1" >> .env
grep -q "^DB_ADMIN_PORT=" .env || echo "DB_ADMIN_PORT=3306" >> .env
grep -q "^DB_ADMIN_USERNAME=" .env || echo "DB_ADMIN_USERNAME=${MTP_DB_ADMIN_USER}" >> .env
grep -q "^DB_ADMIN_PASSWORD=" .env || echo "DB_ADMIN_PASSWORD=${MTP_DB_ADMIN_PASSWORD}" >> .env

if [[ "${MTP_DOMAIN}" != "_" ]]; then
    sed -i "s#^APP_URL=.*#APP_URL=https://${MTP_DOMAIN}#" .env
fi

# ---------------------------------------------------------------------------
log "Building front-end assets"
# ---------------------------------------------------------------------------
npm install
npm run build

# ---------------------------------------------------------------------------
log "Running migrations and seeders"
# ---------------------------------------------------------------------------
# A stale bootstrap/cache/config.php from an earlier attempt (this script is
# safe to re-run, so that's a real scenario) makes Laravel ignore .env
# entirely - clear it first so the DB_* values just written above are
# actually the ones migrate/seed connect with, not whatever was cached
# before .env existed or had different values.
php artisan config:clear
php artisan migrate --force --seed
php artisan storage:link || true

# ---------------------------------------------------------------------------
log "Setting file permissions"
# ---------------------------------------------------------------------------
chown -R "${MTP_SYSTEM_USER}:${MTP_SYSTEM_USER}" "${MTP_APP_DIR}"
chmod -R 775 "${MTP_APP_DIR}/storage" "${MTP_APP_DIR}/bootstrap/cache"

# If MTP_APP_DIR lives under a regular user's home directory (the documented
# quick-start clones straight into $HOME), every ancestor directory needs an
# execute ("traverse") bit for "other" - Ubuntu creates home directories as
# 750 by default, which silently blocks www-data from ever reaching
# public/index.php even though the app directory itself is chowned to it.
# nginx then gets a bare "File not found." from PHP-FPM that looks exactly
# like a routing bug, not a permissions one. o+x alone (no o+r) only allows
# traversal into a known path - it does not expose directory listings.
ancestor="$(dirname "${MTP_APP_DIR}")"
while [[ "${ancestor}" != "/" ]]; do
    chmod o+x "${ancestor}" 2>/dev/null || true
    ancestor="$(dirname "${ancestor}")"
done

# ---------------------------------------------------------------------------
log "Writing the nginx vhost for the panel itself"
# ---------------------------------------------------------------------------
NGINX_SITE_PATH="/etc/nginx/sites-available/mtp-deploy.conf"
cat > "${NGINX_SITE_PATH}" <<NGINX
server {
    listen 80;
    listen [::]:80;

    server_name ${MTP_DOMAIN};
    root ${MTP_APP_DIR}/public;

    index index.php;

    access_log /var/log/nginx/mtp-deploy-access.log;
    error_log /var/log/nginx/mtp-deploy-error.log;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/php${MTP_PHP_VERSION}-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
NGINX

if [[ "${MTP_INSTALL_PHPMYADMIN}" == "yes" && -d /usr/share/phpmyadmin ]]; then
    cat >> "${NGINX_SITE_PATH}" <<'NGINX'

    location /phpmyadmin {
        alias /usr/share/phpmyadmin;
        index index.php;

        location ~ ^/phpmyadmin/(.+\.php)$ {
            alias /usr/share/phpmyadmin/$1;
            fastcgi_pass unix:/run/php/phpMTP_PHP_VERSION-fpm.sock;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $request_filename;
            include fastcgi_params;
        }

        location ~* ^/phpmyadmin/(.+\.(jpg|jpeg|gif|css|png|js|ico|html|xml|txt))$ {
            alias /usr/share/phpmyadmin/$1;
        }
    }
NGINX
    sed -i "s#phpMTP_PHP_VERSION-fpm#php${MTP_PHP_VERSION}-fpm#" "${NGINX_SITE_PATH}"
fi

echo "}" >> "${NGINX_SITE_PATH}"

ln -sf "${NGINX_SITE_PATH}" /etc/nginx/sites-enabled/mtp-deploy.conf
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

# ---------------------------------------------------------------------------
log "Granting the app user write access to nginx vhost dirs and the sites root"
# ---------------------------------------------------------------------------
# Module 3 (Website Manager) writes/removes each website's nginx vhost under
# /etc/nginx/sites-{available,enabled} and creates its document root under
# MTP_SITES_ROOT directly from PHP (WebsiteProvisioningService) - see
# docs/Architecture.md. Both are root-owned (0755) by default, so without
# this a website's database row gets created but nothing ever lands on disk,
# with the only symptom being a "provisioning failed" notification.
MTP_SITES_ROOT="${MTP_SITES_ROOT:-/var/www}"
mkdir -p "${MTP_SITES_ROOT}"
chgrp "${MTP_SYSTEM_USER}" /etc/nginx/sites-available /etc/nginx/sites-enabled "${MTP_SITES_ROOT}"
chmod g+w /etc/nginx/sites-available /etc/nginx/sites-enabled "${MTP_SITES_ROOT}"

# ---------------------------------------------------------------------------
log "Granting the app user passwordless sudo for its whitelisted system commands"
# ---------------------------------------------------------------------------
# app/Enums/WhitelistedOperation.php is the *only* place these three commands
# are ever built, always via `sudo -n` (never a blanket `sudo ALL`) - see
# docs/Architecture.md's "Privileged System Operations" section. Validated
# with `visudo -c` before being installed so a typo here can never corrupt
# sudo's config or lock out sudo entirely.
SUDOERS_FILE="/etc/sudoers.d/mtp-deploy"
cat > "${SUDOERS_FILE}.tmp" <<SUDOERS
${MTP_SYSTEM_USER} ALL=(root) NOPASSWD: /usr/sbin/nginx -t
${MTP_SYSTEM_USER} ALL=(root) NOPASSWD: /usr/bin/systemctl reload nginx
${MTP_SYSTEM_USER} ALL=(root) NOPASSWD: /usr/bin/systemctl restart php${MTP_PHP_VERSION}-fpm
SUDOERS
if visudo -c -f "${SUDOERS_FILE}.tmp"; then
    install -m 0440 "${SUDOERS_FILE}.tmp" "${SUDOERS_FILE}"
fi
rm -f "${SUDOERS_FILE}.tmp"

# ---------------------------------------------------------------------------
log "Carving out a systemd sandbox exception so php-fpm can write nginx vhosts"
# ---------------------------------------------------------------------------
# Ubuntu's php-fpm systemd unit ships with ProtectSystem=full, which makes
# /usr, /boot, and /etc read-only *from php-fpm's own process*, in its own
# mount namespace - completely independent of, and not overridden by, plain
# Unix chmod/chgrp permissions. Without this override, WebsiteProvisioningService's
# direct file_put_contents() call to write a vhost fails with "Read-only file
# system" even though the directory's ordinary permission bits are correct.
# ReadWritePaths carves out exactly the two directories this app needs to
# write to, leaving every other ProtectSystem=full protection intact.
mkdir -p "/etc/systemd/system/php${MTP_PHP_VERSION}-fpm.service.d"
cat > "/etc/systemd/system/php${MTP_PHP_VERSION}-fpm.service.d/mtp-deploy.conf" <<SYSTEMD
[Service]
ReadWritePaths=/etc/nginx/sites-available /etc/nginx/sites-enabled
SYSTEMD
systemctl daemon-reload
systemctl restart "php${MTP_PHP_VERSION}-fpm"

# ---------------------------------------------------------------------------
log "Configuring the Supervisor queue worker"
# ---------------------------------------------------------------------------
cat > /etc/supervisor/conf.d/mtp-deploy-worker.conf <<SUPERVISOR
[program:mtp-deploy-worker]
process_name=%(program_name)s_%(process_num)02d
command=php ${MTP_APP_DIR}/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=${MTP_SYSTEM_USER}
numprocs=1
redirect_stderr=true
stdout_logfile=${MTP_APP_DIR}/storage/logs/worker.log
stopwaitsecs=3600
SUPERVISOR

supervisorctl reread
supervisorctl update
supervisorctl start mtp-deploy-worker:* || true

# ---------------------------------------------------------------------------
log "Installing the scheduler cron entry"
# ---------------------------------------------------------------------------
# Most stock Ubuntu Server images ship with `cron` pre-installed and enabled,
# but a minimal/custom image might not - install and enable it explicitly
# rather than assuming, since the scheduler entry below is useless without it.
apt-get install -y cron
systemctl enable --now cron

CRON_LINE="* * * * * ${MTP_SYSTEM_USER} cd ${MTP_APP_DIR} && php artisan schedule:run >> /dev/null 2>&1"
CRON_FILE="/etc/cron.d/mtp-deploy"
echo "${CRON_LINE}" > "${CRON_FILE}"
chmod 644 "${CRON_FILE}"

# ---------------------------------------------------------------------------
log "Caching configuration for production"
# ---------------------------------------------------------------------------
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ---------------------------------------------------------------------------
echo
echo "======================================================================"
echo " MTP Deploy is installed."
echo "======================================================================"
echo " URL:              http://${MTP_DOMAIN}/admin  (register the first"
echo "                    account there - it's automatically made super-admin)"
if [[ "${MTP_INSTALL_PHPMYADMIN}" == "yes" ]]; then
echo " phpMyAdmin:        http://${MTP_DOMAIN}/phpmyadmin"
fi
echo " App database:      ${MTP_DB_NAME}"
echo " App DB user:        ${MTP_DB_USER}"
echo " App DB password:    ${MTP_DB_PASSWORD}"
echo " Admin DB user:      ${MTP_DB_ADMIN_USER}  (used by Module 4 to manage"
echo "                     other databases - not the same as the app's own DB user)"
echo " Admin DB password:  ${MTP_DB_ADMIN_PASSWORD}"
echo
echo " Both passwords above are also saved in this app's .env file"
echo " (${MTP_APP_DIR}/.env) - copy them somewhere safe now."
echo
echo " Next steps: point this server's SSL (Module 10 in the panel) at a"
echo " real domain, and see INSTALL.md for post-install hardening notes."
echo "======================================================================"
