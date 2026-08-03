<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\NotificationChannelType;
use App\Mail\PlainNotificationMail;
use App\Models\NotificationChannel;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Dispatches a plain subject/body message through one of a user's own
 * notification channels. Telegram/Discord/Slack are third-party SaaS APIs
 * this dev environment has no real bot token/webhook to test end-to-end
 * against - like Module 9's Cloudflare and Module 10's Let's Encrypt, tests
 * use `Http::fake()` against each provider's real, documented request shape
 * rather than a live account round-trip, an honest, disclosed deviation (see
 * CLAUDE.md). Email is fully real and testable via `Mail::fake()`.
 *
 * A failure on one channel never throws - it's logged and reported back as
 * `false` so a broken Telegram bot token doesn't prevent every other enabled
 * channel (or every other user) from being notified.
 */
class NotificationDispatchService
{
    /**
     * @param  Collection<int, User>  $users
     */
    public function notifyUsers(Collection $users, string $subject, string $body): void
    {
        $users->each(fn (User $user) => $this->notifyUser($user, $subject, $body));
    }

    public function notifyUser(User $user, string $subject, string $body): void
    {
        $user->notificationChannels()
            ->where('is_enabled', true)
            ->get()
            ->each(fn (NotificationChannel $channel) => $this->send($channel, $subject, $body));
    }

    public function send(NotificationChannel $channel, string $subject, string $body): bool
    {
        try {
            return match ($channel->channel) {
                NotificationChannelType::Email => $this->sendEmail($channel, $subject, $body),
                NotificationChannelType::Telegram => $this->sendTelegram($channel, $subject, $body),
                NotificationChannelType::Discord => $this->sendDiscord($channel, $subject, $body),
                NotificationChannelType::Slack => $this->sendSlack($channel, $subject, $body),
            };
        } catch (\Throwable $exception) {
            Log::warning('Notification dispatch failed', [
                'channel_id' => $channel->id,
                'channel_type' => $channel->channel->value,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function sendEmail(NotificationChannel $channel, string $subject, string $body): bool
    {
        $recipient = $channel->config['email'] ?? $channel->user->email;

        Mail::to($recipient)->send(new PlainNotificationMail($subject, $body));

        return true;
    }

    private function sendTelegram(NotificationChannel $channel, string $subject, string $body): bool
    {
        $token = $channel->config['bot_token'] ?? null;
        $chatId = $channel->config['chat_id'] ?? null;

        if (! $token || ! $chatId) {
            return false;
        }

        $response = Http::asJson()->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => "{$subject}\n\n{$body}",
        ]);

        return $response->successful() && $response->json('ok') === true;
    }

    private function sendDiscord(NotificationChannel $channel, string $subject, string $body): bool
    {
        $webhookUrl = $channel->config['webhook_url'] ?? null;

        if (! $webhookUrl) {
            return false;
        }

        $response = Http::asJson()->post($webhookUrl, [
            'content' => "**{$subject}**\n{$body}",
        ]);

        return $response->successful();
    }

    private function sendSlack(NotificationChannel $channel, string $subject, string $body): bool
    {
        $webhookUrl = $channel->config['webhook_url'] ?? null;

        if (! $webhookUrl) {
            return false;
        }

        $response = Http::asJson()->post($webhookUrl, [
            'text' => "*{$subject}*\n{$body}",
        ]);

        return $response->successful();
    }
}
