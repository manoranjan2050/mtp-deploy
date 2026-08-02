<?php

declare(strict_types=1);

namespace App\Enums;

enum ServiceStatus: string
{
    case Running = 'running';
    case Stopped = 'stopped';

    /**
     * The service can't be checked on this platform/environment - e.g. nginx
     * and cloudflared are Linux-only production services, unavailable when
     * developing on Windows. Never silently reported as Running or Stopped -
     * see docs/Vision.md: "never lie about server state."
     */
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Running => 'Running',
            self::Stopped => 'Stopped',
            self::Unavailable => 'Unavailable',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Running => 'success',
            self::Stopped => 'danger',
            self::Unavailable => 'gray',
        };
    }
}
