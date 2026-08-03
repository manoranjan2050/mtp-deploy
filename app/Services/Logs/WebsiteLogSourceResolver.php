<?php

declare(strict_types=1);

namespace App\Services\Logs;

use App\Models\Website;
use App\Services\Websites\NginxConfigGeneratorService;

/**
 * Resolves the known, fixed set of log files a website could have - nginx
 * access/error logs (same path convention `NginxConfigGeneratorService`
 * writes into the vhost config) and the website's own Laravel log, if it is
 * a Laravel site. This is a closed, known list, never an arbitrary
 * user-supplied path - the same "fixed allowlist, not free-form input"
 * principle as `WhitelistedOperation` (Module 3).
 */
class WebsiteLogSourceResolver
{
    public function __construct(
        private readonly NginxConfigGeneratorService $nginxConfig,
    ) {}

    /**
     * @return array<string, string> label => absolute path
     */
    public function sources(Website $website): array
    {
        $sources = [
            'nginx access log' => $this->nginxConfig->accessLogPath($website),
            'nginx error log' => $this->nginxConfig->errorLogPath($website),
        ];

        if ($website->framework->value === 'laravel') {
            $sources['Laravel log'] = rtrim(str_replace('\\', '/', $website->document_root), '/').'/storage/logs/laravel.log';
        }

        return $sources;
    }
}
