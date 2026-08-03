<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DeploymentTrigger: string implements HasLabel
{
    case Manual = 'manual';
    case Webhook = 'webhook';
    case Api = 'api';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Webhook => 'Webhook',
            self::Api => 'API',
        };
    }
}
