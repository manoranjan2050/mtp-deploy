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

];
