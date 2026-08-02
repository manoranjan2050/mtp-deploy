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

];
