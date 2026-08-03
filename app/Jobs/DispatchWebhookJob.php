<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\WebhookEvent;
use App\Models\WebhookEndpoint;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/**
 * Real HMAC-signed outbound HTTP POST - not a placeholder. Retried with
 * backoff on failure (a 4xx/5xx response or a network error) since the
 * receiving endpoint is a third-party URL this app doesn't control and can't
 * assume is always reachable on the first attempt.
 */
class DispatchWebhookJob implements ShouldQueue
{
    use Queueable;

    /**
     * @var int
     */
    public $tries = 5;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly WebhookEndpoint $endpoint,
        public readonly WebhookEvent $event,
        public readonly array $payload,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 300, 900];
    }

    public function handle(): void
    {
        $body = json_encode([
            'event' => $this->event->value,
            'data' => $this->payload,
        ]);

        $signature = hash_hmac('sha256', $body, $this->endpoint->secret);

        Http::withBody($body, 'application/json')
            ->withHeaders(['X-MTP-Signature' => "sha256={$signature}"])
            ->timeout(10)
            ->post($this->endpoint->url)
            ->throw();
    }
}
