<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DeploymentProvider: string implements HasLabel
{
    case GitHub = 'github';
    case GitLab = 'gitlab';
    case Bitbucket = 'bitbucket';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::GitHub => 'GitHub',
            self::GitLab => 'GitLab',
            self::Bitbucket => 'Bitbucket',
            self::Manual => 'Manual / other',
        };
    }
}
