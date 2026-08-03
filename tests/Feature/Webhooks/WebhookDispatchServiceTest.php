<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Enums\WebhookEvent;
use App\Jobs\DispatchWebhookJob;
use App\Models\User;
use App\Services\Webhooks\WebhookDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookDispatchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_a_job_for_each_enabled_endpoint_subscribed_to_the_event(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $endpoint = $user->webhookEndpoints()->create([
            'url' => 'https://example.test/hook',
            'secret' => 'shh',
            'events' => [WebhookEvent::DeploymentSucceeded->value],
        ]);

        app(WebhookDispatchService::class)->dispatchForUser($user, WebhookEvent::DeploymentSucceeded, ['foo' => 'bar']);

        Queue::assertPushed(DispatchWebhookJob::class, fn (DispatchWebhookJob $job) => $job->endpoint->is($endpoint));
    }

    public function test_it_skips_an_endpoint_not_subscribed_to_the_event(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $user->webhookEndpoints()->create([
            'url' => 'https://example.test/hook',
            'secret' => 'shh',
            'events' => [WebhookEvent::DeploymentFailed->value],
        ]);

        app(WebhookDispatchService::class)->dispatchForUser($user, WebhookEvent::DeploymentSucceeded, []);

        Queue::assertNotPushed(DispatchWebhookJob::class);
    }

    public function test_it_skips_a_disabled_endpoint(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $user->webhookEndpoints()->create([
            'url' => 'https://example.test/hook',
            'secret' => 'shh',
            'events' => [WebhookEvent::DeploymentSucceeded->value],
            'is_enabled' => false,
        ]);

        app(WebhookDispatchService::class)->dispatchForUser($user, WebhookEvent::DeploymentSucceeded, []);

        Queue::assertNotPushed(DispatchWebhookJob::class);
    }
}
