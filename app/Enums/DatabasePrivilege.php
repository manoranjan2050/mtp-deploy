<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The grantable privilege set exposed in the panel - a deliberate subset of
 * MySQL's full GRANT vocabulary (no FILE, SUPER, PROCESS, etc. - nothing that
 * reaches outside the single granted database).
 */
enum DatabasePrivilege: string implements HasLabel
{
    case Select = 'SELECT';
    case Insert = 'INSERT';
    case Update = 'UPDATE';
    case Delete = 'DELETE';
    case Create = 'CREATE';
    case Drop = 'DROP';
    case Alter = 'ALTER';
    case Index = 'INDEX';
    case AllPrivileges = 'ALL PRIVILEGES';

    public function getLabel(): string
    {
        return match ($this) {
            self::AllPrivileges => 'All privileges',
            default => ucfirst(strtolower($this->value)),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $privilege): array => [$privilege->value => $privilege->getLabel()])
            ->all();
    }
}
