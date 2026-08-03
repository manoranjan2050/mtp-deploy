<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Server;
use App\Models\User;

class ServerPolicy
{
    /**
     * Shell access is admin/super-admin only - unlike Website/Database, this
     * isn't scoped to "sites I created" for developers, since a terminal on
     * the underlying server reaches every site and every database on it, far
     * beyond what a developer role is trusted with.
     */
    public function useTerminal(User $user, Server $server): bool
    {
        return $user->can('use terminal');
    }

    /**
     * Same reasoning as useTerminal(): a tunnel exposes the whole server, not
     * one site, so this is admin/super-admin only regardless of who created
     * which website on it.
     */
    public function manageTunnels(User $user, Server $server): bool
    {
        return $user->can('manage cloudflare tunnels');
    }

    /**
     * Same reasoning again: a cron job is an arbitrary command executed on a
     * schedule with no confirmation step at run time - the same trust level
     * as Terminal, not something delegated to the developer role.
     */
    public function manageCronJobs(User $user, Server $server): bool
    {
        return $user->can('manage cron jobs');
    }

    /**
     * Viewing live/historical metrics and the process list is open to any
     * authenticated user, same as the Dashboard's own system-stats widgets -
     * these are read-only operational info, not a mutation. Only setting
     * alert thresholds and manually resolving an alert require this
     * ability - a system-wide config change, the same trust level as
     * Terminal/Cron/Tunnels, not delegated to `developer`/`viewer`.
     */
    public function manageMonitoringAlerts(User $user, Server $server): bool
    {
        return $user->can('manage monitoring alerts');
    }
}
