<?php

declare(strict_types=1);

namespace App\Enums;

use InvalidArgumentException;

/**
 * The fixed, closed set of shell operations MTP Deploy is ever allowed to run.
 * `SystemCommandService` is the only class permitted to turn one of these into
 * a `Symfony\Component\Process\Process` - see docs/Architecture.md's
 * "Privileged System Operations" section and docs/Security.md. There is no
 * "escape hatch" to run an arbitrary command; every new capability requires a
 * new case here, reviewed like any other security-relevant change.
 */
enum WhitelistedOperation: string
{
    case ReloadNginx = 'reload_nginx';
    case TestNginxConfig = 'test_nginx_config';
    case RestartPhpFpm = 'restart_php_fpm';

    /**
     * @param  array<string, string>  $arguments
     * @return list<string>
     */
    public function commandFor(array $arguments = []): array
    {
        return match ($this) {
            self::ReloadNginx => ['sudo', '-n', 'systemctl', 'reload', 'nginx'],
            self::TestNginxConfig => ['sudo', '-n', 'nginx', '-t'],
            self::RestartPhpFpm => ['sudo', '-n', 'systemctl', 'restart', 'php'.$this->requireArgument($arguments, 'php_version').'-fpm'],
        };
    }

    /**
     * @param  array<string, string>  $arguments
     */
    private function requireArgument(array $arguments, string $key): string
    {
        if (! isset($arguments[$key]) || ! preg_match('/^\d+\.\d+$/', $arguments[$key])) {
            throw new InvalidArgumentException("Operation {$this->value} requires a valid '{$key}' argument (e.g. \"8.3\").");
        }

        return $arguments[$key];
    }
}
