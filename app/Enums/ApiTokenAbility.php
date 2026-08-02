<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Scopes grantable to a personal access token. Grows as each module ships its
 * own API surface (see docs/API.md) - Module 1 only has read-only self-service
 * abilities since there is nothing else to authorize against yet.
 */
enum ApiTokenAbility: string
{
    case ViewProfile = 'profile:read';
    case ManageSessions = 'sessions:write';
    case FullAccess = '*';

    public function label(): string
    {
        return match ($this) {
            self::ViewProfile => 'View my profile',
            self::ManageSessions => 'Manage my sessions',
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
