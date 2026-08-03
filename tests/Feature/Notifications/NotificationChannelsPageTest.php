<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\NotificationChannelType;
use App\Filament\Pages\Profile\NotificationChannels;
use App\Mail\PlainNotificationMail;
use App\Models\NotificationChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationChannelsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_add_an_email_channel(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(NotificationChannels::class)
            ->callTableAction('create', data: ['channel' => NotificationChannelType::Email->value, 'label' => 'Work email']);

        $this->assertDatabaseHas('notification_channels', ['user_id' => $user->id, 'channel' => 'email', 'label' => 'Work email']);
    }

    public function test_a_user_can_add_a_telegram_channel_with_its_config(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(NotificationChannels::class)
            ->callTableAction('create', data: [
                'channel' => NotificationChannelType::Telegram->value,
                'config' => ['bot_token' => 't0ken', 'chat_id' => '999'],
            ]);

        $channel = NotificationChannel::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('t0ken', $channel->config['bot_token']);
        $this->assertSame('999', $channel->config['chat_id']);
    }

    public function test_a_user_only_sees_their_own_channels(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $other->notificationChannels()->create(['channel' => NotificationChannelType::Email, 'config' => []]);
        $mine = $user->notificationChannels()->create(['channel' => NotificationChannelType::Email, 'config' => [], 'label' => 'Mine']);

        Livewire::actingAs($user)
            ->test(NotificationChannels::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCountTableRecords(1);
    }

    public function test_a_user_can_toggle_a_channel_enabled(): void
    {
        $user = User::factory()->create();
        $channel = $user->notificationChannels()->create(['channel' => NotificationChannelType::Email, 'config' => [], 'is_enabled' => true]);

        Livewire::actingAs($user)
            ->test(NotificationChannels::class)
            ->callTableAction('toggle', $channel);

        $this->assertFalse($channel->fresh()->is_enabled);
    }

    public function test_a_user_can_delete_a_channel(): void
    {
        $user = User::factory()->create();
        $channel = $user->notificationChannels()->create(['channel' => NotificationChannelType::Email, 'config' => []]);

        Livewire::actingAs($user)
            ->test(NotificationChannels::class)
            ->callTableAction('delete', $channel);

        $this->assertDatabaseMissing('notification_channels', ['id' => $channel->id]);
    }

    public function test_send_test_reports_success_for_a_working_channel(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $channel = $user->notificationChannels()->create(['channel' => NotificationChannelType::Email, 'config' => []]);

        Livewire::actingAs($user)
            ->test(NotificationChannels::class)
            ->callTableAction('sendTest', $channel)
            ->assertNotified();

        Mail::assertSent(fn (PlainNotificationMail $mailable) => $mailable->hasTo($user->email));
    }

    public function test_send_test_reports_failure_for_a_broken_telegram_channel(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 400)]);

        $user = User::factory()->create();
        $channel = $user->notificationChannels()->create([
            'channel' => NotificationChannelType::Telegram,
            'config' => ['bot_token' => 'bad', 'chat_id' => '1'],
        ]);

        Livewire::actingAs($user)
            ->test(NotificationChannels::class)
            ->callTableAction('sendTest', $channel)
            ->assertNotified();
    }
}
