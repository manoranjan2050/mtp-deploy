<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\Enums\WebhookEvent;
use App\Jobs\DispatchWebhookJob;
use App\Models\User;

/**
 * Queues a signed webhook POST to every one of a user's own enabled
 * endpoints subscribed to the given event - never a global broadcast to
 * every endpoint in the system, same self-scoped principle as Module 16's
 * NotificationDispatchService.
 */
class WebhookDispatchService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatchForUser(User $user, WebhookEvent $event, array $payload): void
    {
        $user->webhookEndpoints()
            ->where('is_enabled', true)
            ->get()
            ->filter(fn ($endpoint) => $endpoint->isSubscribedTo($event->value))
            ->each(fn ($endpoint) => DispatchWebhookJob::dispatch($endpoint, $event, $payload));
    }
}
