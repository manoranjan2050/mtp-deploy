<?php

declare(strict_types=1);

namespace App\Services\Websites;

use App\Enums\SslStatus;
use App\Enums\WebsiteStatus;
use App\Models\Website;
use App\Services\Ssl\CertificateStorageService;

/**
 * Pure string generation - no filesystem or network I/O, so it's fully unit
 * testable without a real server. Writing the result to disk and reloading
 * nginx are separate concerns, owned by WebsiteProvisioningService.
 */
class NginxConfigGeneratorService
{
    public function __construct(
        private readonly CertificateStorageService $certificateStorage,
    ) {}

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
            ->unique()
            ->implode(' ');

        $socket = "/var/run/php/php{$website->php_version}-fpm.sock";

        $listen = $website->ssl_status === SslStatus::Active
            ? "listen 443 ssl;\n            listen [::]:443 ssl;"
            : "listen 80;\n            listen [::]:80;";

        $sslDirectives = $website->ssl_status === SslStatus::Active
            ? $this->sslDirectives($website)
            : '';

        $redirect = $website->ssl_status === SslStatus::Active
            ? $this->httpToHttpsRedirectBlock($serverNames)
            : '';

        return <<<NGINX
        # Managed by MTP Deploy - do not edit outside the panel; changes will
        # be overwritten on the next save.
        {$redirect}server {
            {$listen}

            server_name {$serverNames};
            root {$website->publicPath()};
        {$sslDirectives}
            index index.php index.html;

            access_log {$this->accessLogPath($website)};
            error_log {$this->errorLogPath($website)};

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

    private function sslDirectives(Website $website): string
    {
        $paths = $this->certificateStorage->paths($website);

        return <<<NGINX


            ssl_certificate {$paths['certificate']};
            ssl_certificate_key {$paths['privateKey']};
            ssl_protocols TLSv1.2 TLSv1.3;

        NGINX;
    }

    private function httpToHttpsRedirectBlock(string $serverNames): string
    {
        return <<<NGINX
        server {
            listen 80;
            listen [::]:80;

            server_name {$serverNames};

            location / {
                return 301 https://\$host\$request_uri;
            }
        }

        NGINX;
    }

    public function accessLogPath(Website $website): string
    {
        return rtrim((string) config('mtp.nginx_log_path'), '/')."/{$website->domain}-access.log";
    }

    public function errorLogPath(Website $website): string
    {
        return rtrim((string) config('mtp.nginx_log_path'), '/')."/{$website->domain}-error.log";
    }

    private function generateSuspended(Website $website): string
    {
        $serverNames = collect([$website->domain, ...($website->aliases ?? [])])
            ->unique()
            ->implode(' ');

        return <<<NGINX
        # Managed by MTP Deploy - this site is SUSPENDED. Serves a 503 to every
        # request instead of the live document root. Reinstate the website in
        # the panel to restore normal serving.
        server {
            listen 80;
            listen [::]:80;

            server_name {$serverNames};

            access_log {$this->accessLogPath($website)};
            error_log {$this->errorLogPath($website)};

            location / {
                return 503 "This site has been suspended.";
            }
        }

        NGINX;
    }
}
