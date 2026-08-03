<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Notifications;

use App\Enums\NotificationChannelType;
use App\Mail\PlainNotificationMail;
use App\Models\NotificationChannel;
use App\Models\User;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Telegram/Discord/Slack are third-party SaaS APIs this dev environment has
 * no real bot token/webhook to test end-to-end against - `Http::fake()`
 * verifies each provider's real, documented request shape instead, the same
 * disclosed deviation as Module 9's Cloudflare and Module 10's Let's
 * Encrypt. Email is genuinely real, verified via `Mail::fake()`.
 */
class NotificationDispatchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_a_real_email_to_the_users_own_address_by_default(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'owner@example.test']);
        $channel = $this->channel($user, NotificationChannelType::Email, []);

        $sent = app(NotificationDispatchService::class)->send($channel, 'Subject', 'Body');

        $this->assertTrue($sent);
        Mail::assertSent(function (PlainNotificationMail $mailable) {
            return $mailable->hasTo('owner@example.test');
        });
    }

    public function test_it_sends_email_to_a_configured_override_address(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'owner@example.test']);
        $channel = $this->channel($user, NotificationChannelType::Email, ['email' => 'ops@example.test']);

        app(NotificationDispatchService::class)->send($channel, 'Subject', 'Body');

        Mail::assertSent(function (PlainNotificationMail $mailable) {
            return $mailable->hasTo('ops@example.test');
        });
    }

    public function test_it_posts_to_the_real_telegram_bot_api_shape(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $user = User::factory()->create();
        $channel = $this->channel($user, NotificationChannelType::Telegram, ['bot_token' => 't0ken', 'chat_id' => '123']);

        $sent = app(NotificationDispatchService::class)->send($channel, 'Subject', 'Body');

        $this->assertTrue($sent);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.telegram.org/bott0ken/sendMessage'
                && $request['chat_id'] === '123'
                && str_contains($request['text'], 'Subject');
        });
    }

    public function test_it_returns_false_when_telegram_reports_failure(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 400)]);

        $user = User::factory()->create();
        $channel = $this->channel($user, NotificationChannelType::Telegram, ['bot_token' => 't0ken', 'chat_id' => '123']);

        $this->assertFalse(app(NotificationDispatchService::class)->send($channel, 'Subject', 'Body'));
    }

    public function test_it_posts_to_a_discord_webhook(): void
    {
        Http::fake(['discord.com/*' => Http::response('', 204)]);

        $user = User::factory()->create();
        $channel = $this->channel($user, NotificationChannelType::Discord, ['webhook_url' => 'https://discord.com/api/webhooks/1/abc']);

        $sent = app(NotificationDispatchService::class)->send($channel, 'Subject', 'Body');

        $this->assertTrue($sent);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://discord.com/api/webhooks/1/abc'
                && str_contains($request['content'], 'Subject');
        });
    }

    public function test_it_posts_to_a_slack_webhook(): void
    {
        Http::fake(['hooks.slack.com/*' => Http::response('ok')]);

        $user = User::factory()->create();
        $channel = $this->channel($user, NotificationChannelType::Slack, ['webhook_url' => 'https://hooks.slack.com/services/abc']);

        $sent = app(NotificationDispatchService::class)->send($channel, 'Subject', 'Body');

        $this->assertTrue($sent);
        Http::assertSent(function ($request) {
            return str_contains($request['text'], 'Subject');
        });
    }

    public function test_a_disabled_channel_is_skipped_when_notifying_a_user(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $this->channel($user, NotificationChannelType::Email, [], isEnabled: false);

        app(NotificationDispatchService::class)->notifyUser($user, 'Subject', 'Body');

        Mail::assertNothingSent();
    }

    public function test_notify_users_dispatches_to_every_users_enabled_channels(): void
    {
        Mail::fake();

        $first = User::factory()->create(['email' => 'a@example.test']);
        $second = User::factory()->create(['email' => 'b@example.test']);
        $this->channel($first, NotificationChannelType::Email, []);
        $this->channel($second, NotificationChannelType::Email, []);

        app(NotificationDispatchService::class)->notifyUsers(collect([$first, $second]), 'Subject', 'Body');

        Mail::assertSent(fn (PlainNotificationMail $mailable) => $mailable->hasTo('a@example.test'));
        Mail::assertSent(fn (PlainNotificationMail $mailable) => $mailable->hasTo('b@example.test'));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function channel(User $user, NotificationChannelType $type, array $config, bool $isEnabled = true): NotificationChannel
    {
        return $user->notificationChannels()->create([
            'channel' => $type,
            'config' => $config,
            'is_enabled' => $isEnabled,
        ]);
    }
}
