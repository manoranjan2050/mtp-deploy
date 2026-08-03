<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NotificationChannelType: string implements HasLabel
{
    case Email = 'email';
    case Telegram = 'telegram';
    case Discord = 'discord';
    case Slack = 'slack';

    public function getLabel(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Telegram => 'Telegram',
            self::Discord => 'Discord',
            self::Slack => 'Slack',
        };
    }
}
