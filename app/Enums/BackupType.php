<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BackupType: string implements HasColor, HasLabel
{
    case Files = 'files';
    case Database = 'database';
    case Full = 'full';
    case GitSnapshot = 'git';

    public function getLabel(): string
    {
        return match ($this) {
            self::Files => 'Files only',
            self::Database => 'Database only',
            self::Full => 'Full (files + databases)',
            self::GitSnapshot => 'Git snapshot',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Files => 'info',
            self::Database => 'warning',
            self::Full => 'success',
            self::GitSnapshot => 'gray',
        };
    }
}
