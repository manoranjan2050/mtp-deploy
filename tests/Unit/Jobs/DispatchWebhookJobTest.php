<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Enums\WebhookEvent;
use App\Jobs\DispatchWebhookJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DispatchWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_posts_a_correctly_signed_payload(): void
    {
        Http::fake(['example.test/*' => Http::response('', 200)]);

        $user = User::factory()->create();
        $endpoint = $user->webhookEndpoints()->create([
            'url' => 'https://example.test/hook',
            'secret' => 'top-secret',
            'events' => [WebhookEvent::DeploymentSucceeded->value],
        ]);

        (new DispatchWebhookJob($endpoint, WebhookEvent::DeploymentSucceeded, ['website_id' => 1]))->handle();

        Http::assertSent(function ($request) {
            $body = $request->body();
            $expectedSignature = hash_hmac('sha256', $body, 'top-secret');

            return $request->url() === 'https://example.test/hook'
                && $request->hasHeader('X-MTP-Signature', "sha256={$expectedSignature}")
                && json_decode($body, true)['event'] === 'deployment.succeeded'
                && json_decode($body, true)['data']['website_id'] === 1;
        });
    }

    public function test_it_throws_on_a_failed_response_so_the_job_retries(): void
    {
        Http::fake(['example.test/*' => Http::response('', 500)]);

        $user = User::factory()->create();
        $endpoint = $user->webhookEndpoints()->create([
            'url' => 'https://example.test/hook',
            'secret' => 'top-secret',
            'events' => [WebhookEvent::DeploymentSucceeded->value],
        ]);

        $this->expectException(RequestException::class);

        (new DispatchWebhookJob($endpoint, WebhookEvent::DeploymentSucceeded, []))->handle();
    }
}
