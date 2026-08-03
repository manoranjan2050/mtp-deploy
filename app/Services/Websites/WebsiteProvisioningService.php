<?php

declare(strict_types=1);

namespace App\Services\Websites;

use App\DTOs\System\SystemCommandResult;
use App\Enums\WhitelistedOperation;
use App\Models\Website;
use App\Services\System\SystemCommandService;
use Illuminate\Support\Facades\File;

/**
 * Orchestrates the filesystem + privileged-command side of managing a
 * website: writing/removing its nginx vhost, creating its document root, and
 * telling nginx to reload. Vhost content itself comes from
 * NginxConfigGeneratorService (pure, no I/O); this class is where the I/O
 * happens.
 *
 * File writes here are plain PHP filesystem calls, not SystemCommandService -
 * per docs/Architecture.md that class is reserved for genuinely privileged
 * shell operations (service reload/restart). In production the paths in
 * config('mtp.*') point at real system directories the app user has been
 * granted write access to (or a setup script pre-creates with the right
 * ownership) - this is a deliberate, narrower alternative to routing every
 * file write through a root-owned helper script, and is revisited if it
 * proves insufficient once this runs against a real server in Module 18.
 */
class WebsiteProvisioningService
{
    public function __construct(
        private readonly NginxConfigGeneratorService $configGenerator,
        private readonly SystemCommandService $commands,
    ) {}

    public function provision(Website $website): SystemCommandResult
    {
        File::ensureDirectoryExists($website->publicPath());

        $this->writeVirtualHost($website, $this->configGenerator->generate($website));

        return $this->applyNginxChanges();
    }

    public function deprovision(Website $website): SystemCommandResult
    {
        $this->removeVirtualHost($website);

        return $this->applyNginxChanges();
    }

    public function republish(Website $website): SystemCommandResult
    {
        File::ensureDirectoryExists($website->publicPath());

        $this->writeVirtualHost($website, $this->configGenerator->generate($website));

        return $this->applyNginxChanges();
    }

    public function restartPhpFpm(string $phpVersion): SystemCommandResult
    {
        return $this->commands->run(WhitelistedOperation::RestartPhpFpm, ['php_version' => $phpVersion]);
    }

    public function reloadNginx(): SystemCommandResult
    {
        return $this->applyNginxChanges();
    }

    private function writeVirtualHost(Website $website, string $config): void
    {
        File::ensureDirectoryExists(config('mtp.nginx_sites_available_path'));
        File::ensureDirectoryExists(config('mtp.nginx_sites_enabled_path'));

        File::put($this->availablePath($website), $config);

        $enabledPath = $this->enabledPath($website);

        if (! File::exists($enabledPath)) {
            File::link($this->availablePath($website), $enabledPath);
        }
    }

    private function removeVirtualHost(Website $website): void
    {
        File::delete($this->enabledPath($website));
        File::delete($this->availablePath($website));
    }

    private function applyNginxChanges(): SystemCommandResult
    {
        $test = $this->commands->run(WhitelistedOperation::TestNginxConfig);

        if (! $test->successful) {
            return $test;
        }

        return $this->commands->run(WhitelistedOperation::ReloadNginx);
    }

    private function availablePath(Website $website): string
    {
        return rtrim(config('mtp.nginx_sites_available_path'), '/')."/{$website->domain}.conf";
    }

    private function enabledPath(Website $website): string
    {
        return rtrim(config('mtp.nginx_sites_enabled_path'), '/')."/{$website->domain}.conf";
    }
}
