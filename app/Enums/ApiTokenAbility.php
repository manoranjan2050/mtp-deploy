<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Scopes grantable to a personal access token. Grows as each module ships its
 * own API surface (see docs/API.md) - Module 17 adds the first real
 * resource-scoped abilities alongside Module 1's self-service ones.
 */
enum ApiTokenAbility: string
{
    case ViewProfile = 'profile:read';
    case ManageSessions = 'sessions:write';
    case WebsitesRead = 'websites:read';
    case WebsitesWrite = 'websites:write';
    case DeploymentsRead = 'deployments:read';
    case DeploymentsWrite = 'deployments:write';
    case WebhooksWrite = 'webhooks:write';
    case FullAccess = '*';

    public function label(): string
    {
        return match ($this) {
            self::ViewProfile => 'View my profile',
            self::ManageSessions => 'Manage my sessions',
            self::WebsitesRead => 'View websites',
            self::WebsitesWrite => 'Manage websites',
            self::DeploymentsRead => 'View deployments',
            self::DeploymentsWrite => 'Trigger/rollback deployments',
            self::WebhooksWrite => 'Manage my outbound webhooks',
            self::FullAccess => 'Full access',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $ability): array => [$ability->value => $ability->label()])
            ->all();
    }
}
