<?php

declare(strict_types=1);

namespace App\Services\Websites;

use App\Enums\WebsiteStatus;
use App\Models\Website;

/**
 * Pure string generation - no filesystem or network I/O, so it's fully unit
 * testable without a real server. Writing the result to disk and reloading
 * nginx are separate concerns, owned by WebsiteProvisioningService.
 */
class NginxConfigGeneratorService
{
    /**
     * Dispatches on the website's current status, so callers always get the
     * config that matches its actual state - a suspended site never
     * accidentally serves live content because someone forgot to pick the
     * "suspended" variant.
     */
    public function generate(Website $website): string
    {
        return match ($website->status) {
            WebsiteStatus::Suspended => $this->generateSuspended($website),
            WebsiteStatus::Active => $this->generateActive($website),
        };
    }

    private function generateActive(Website $website): string
    {
        $serverNames = collect([$website->domain, ...($website->aliases ?? [])])
            ->implode(' ');

        $socket = "/var/run/php/php{$website->php_version}-fpm.sock";

        return <<<NGINX
        # Managed by MTP Deploy - do not edit outside the panel; changes will
        # be overwritten on the next save.
        server {
            listen 80;
            listen [::]:80;

            server_name {$serverNames};
            root {$website->publicPath()};

            index index.php index.html;

            access_log /var/log/nginx/{$website->domain}-access.log;
            error_log /var/log/nginx/{$website->domain}-error.log;

            location / {
                try_files \$uri \$uri/ /index.php?\$query_string;
            }

            location ~ \.php\$ {
                fastcgi_pass unix:{$socket};
                fastcgi_index index.php;
                fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
                include fastcgi_params;
            }

            location ~ /\.(?!well-known).* {
                deny all;
            }
        }

        NGINX;
    }

    private function generateSuspended(Website $website): string
    {
        $serverNames = collect([$website->domain, ...($website->aliases ?? [])])
            ->implode(' ');

        return <<<NGINX
        # Managed by MTP Deploy - this site is SUSPENDED. Serves a 503 to every
        # request instead of the live document root. Reinstate the website in
        # the panel to restore normal serving.
        server {
            listen 80;
            listen [::]:80;

            server_name {$serverNames};

            access_log /var/log/nginx/{$website->domain}-access.log;
            error_log /var/log/nginx/{$website->domain}-error.log;

            location / {
                return 503 "This site has been suspended.";
            }
        }

        NGINX;
    }
}
