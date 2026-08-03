<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Website provisioning paths
    |--------------------------------------------------------------------------
    |
    | Where MTP Deploy writes nginx vhost configs and expects site document
    | roots to live on a managed server. On a real Ubuntu/Debian box these
    | match the distro's nginx package layout. Overridable per-environment so
    | tests (and local, non-Linux dev) can point these at a temp directory
    | instead of writing to protected system paths - see
    | tests/Feature/Websites/WebsiteProvisioningServiceTest.php.
    |
    */

    'nginx_sites_available_path' => env('MTP_NGINX_SITES_AVAILABLE_PATH', '/etc/nginx/sites-available'),

    'nginx_sites_enabled_path' => env('MTP_NGINX_SITES_ENABLED_PATH', '/etc/nginx/sites-enabled'),

    'sites_root' => env('MTP_SITES_ROOT', '/var/www'),

    /*
    |--------------------------------------------------------------------------
    | Database Manager (Module 4)
    |--------------------------------------------------------------------------
    */

    'mysqldump_path' => env('MYSQLDUMP_PATH', 'mysqldump'),

    'mysql_cli_path' => env('MYSQL_CLI_PATH', 'mysql'),

    'database_backups_path' => env('MTP_DATABASE_BACKUPS_PATH', storage_path('app/database-backups')),

    /*
    |--------------------------------------------------------------------------
    | Terminal (Module 8)
    |--------------------------------------------------------------------------
    |
    | Starting working directory for a new terminal session, before any `cd`.
    | On a real Linux server this would sensibly be `/root` or `/home/{user}`;
    | this default is a harmless stand-in for local, non-Linux dev - tests
    | always override it to a temp directory.
    |
    */

    'terminal_default_directory' => env('MTP_TERMINAL_DEFAULT_DIRECTORY', sys_get_temp_dir()),

    /*
    |--------------------------------------------------------------------------
    | Terminal command timeout (seconds)
    |--------------------------------------------------------------------------
    |
    | Every terminal command runs as a one-shot process with this hard
    | timeout, so a hung/interactive command (e.g. one that waits on stdin
    | this app never supplies) can't block a session forever.
    |
    */

    'terminal_command_timeout' => env('MTP_TERMINAL_COMMAND_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | SSL (Module 10)
    |--------------------------------------------------------------------------
    |
    | Where issued/uploaded certificate + private key PEM files are written on
    | disk for nginx to reference. Defaults to the ACME **staging** directory,
    | not production - staging has no rate limits and issues certs signed by
    | an untrusted test CA, which is exactly what a dev/CI environment should
    | talk to. Point MTP_ACME_DIRECTORY_URL at the production URL
    | (https://acme-v02.api.letsencrypt.org/directory) only on a real server
    | with a real public domain.
    |
    */

    'ssl_certificates_path' => env('MTP_SSL_CERTIFICATES_PATH', storage_path('app/ssl-certificates')),

    'ssl_renewal_threshold_days' => env('MTP_SSL_RENEWAL_THRESHOLD_DAYS', 30),

    'acme_directory_url' => env('MTP_ACME_DIRECTORY_URL', 'https://acme-staging-v02.api.letsencrypt.org/directory'),

    /*
    |--------------------------------------------------------------------------
    | OpenSSL config file path (Windows/AMPPS dev quirk)
    |--------------------------------------------------------------------------
    |
    | This machine's PHP build has no working default openssl.cnf wired into
    | php.ini, so openssl_pkey_new()/openssl_csr_new() fail outright with
    | "system library: No such process" until an explicit config path is
    | passed in their options array. A real Linux server's PHP build normally
    | has this working out of the box and needs no override - see CLAUDE.md.
    |
    */

    'openssl_config_path' => env('MTP_OPENSSL_CONFIG_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Backups (Module 13)
    |--------------------------------------------------------------------------
    |
    | Zip archives (file backups) and bare git "shadow" repositories (git
    | snapshot backups) both live under storage/, never inside a website's own
    | document root - a backup that lived alongside the thing it backs up
    | would be lost in the same disk failure/`rm -rf` it's meant to protect
    | against.
    |
    */

    'website_backups_path' => env('MTP_WEBSITE_BACKUPS_PATH', storage_path('app/website-backups')),

    'git_backups_path' => env('MTP_GIT_BACKUPS_PATH', storage_path('app/git-backups')),

    /*
    |--------------------------------------------------------------------------
    | Project info / update checker
    |--------------------------------------------------------------------------
    |
    | Used by the About page and the update-checker: compares this
    | installation's checked-out git commit against the latest commit on the
    | public repo's default branch via GitHub's public REST API (no token
    | needed for a public repo, so this works out of the box - just rate
    | limited to 60 unauthenticated requests/hour per IP, hence the cache).
    |
    */

    'github_repo' => env('MTP_GITHUB_REPO', 'manoranjan2050/mtp-deploy'),

    'github_branch' => env('MTP_GITHUB_BRANCH', 'main'),

];
