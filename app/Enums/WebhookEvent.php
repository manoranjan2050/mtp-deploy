<?php

declare(strict_types=1);

namespace App\Enums;

enum WebhookEvent: string
{
    case DeploymentSucceeded = 'deployment.succeeded';
    case DeploymentFailed = 'deployment.failed';

    public function label(): string
    {
        return match ($this) {
            self::DeploymentSucceeded => 'Deployment succeeded',
            self::DeploymentFailed => 'Deployment failed',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $event): array => [$event->value => $event->label()])
            ->all();
    }
}
