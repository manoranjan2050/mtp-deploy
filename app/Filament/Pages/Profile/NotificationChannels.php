<?php

declare(strict_types=1);

namespace App\Filament\Pages\Profile;

use App\Enums\NotificationChannelType;
use App\Models\NotificationChannel;
use App\Models\User;
use App\Services\Notifications\NotificationDispatchService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NotificationChannels extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.profile.notification-channels';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|\UnitEnum|null $navigationGroup = 'Account';

    protected static ?int $navigationSort = 22;

    protected static ?string $title = 'Notification Channels';

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        return $table
            ->query(fn (): Builder => $user->notificationChannels()->getQuery())
            ->columns([
                TextColumn::make('channel')
                    ->badge(),
                TextColumn::make('label')
                    ->placeholder('—'),
                IconColumn::make('is_enabled')
                    ->label('Enabled')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime(),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Add channel')
                    ->icon(Heroicon::OutlinedPlus)
                    ->schema([
                        Select::make('channel')
                            ->options(NotificationChannelType::class)
                            ->required(),
                        TextInput::make('label')
                            ->label('Label')
                            ->placeholder('e.g. "Personal phone"')
                            ->maxLength(255),
                        TextInput::make('config.email')
                            ->label('Email: recipient address (blank = your account email)')
                            ->email(),
                        TextInput::make('config.bot_token')
                            ->label('Telegram: bot token'),
                        TextInput::make('config.chat_id')
                            ->label('Telegram: chat ID'),
                        TextInput::make('config.webhook_url')
                            ->label('Discord/Slack: webhook URL')
                            ->url(),
                    ])
                    ->action(function (array $data): void {
                        /** @var User $user */
                        $user = auth()->user();
                        $channel = $data['channel'] instanceof NotificationChannelType
                            ? $data['channel']
                            : NotificationChannelType::from($data['channel']);

                        $user->notificationChannels()->create([
                            'channel' => $channel,
                            'label' => $data['label'] ?? null,
                            'config' => $this->configFor($channel, $data),
                        ]);

                        Notification::make()->title('Channel added')->success()->send();

                        $this->resetTable();
                    }),
            ])
            ->recordActions([
                Action::make('toggle')
                    ->label(fn (NotificationChannel $record): string => $record->is_enabled ? 'Disable' : 'Enable')
                    ->icon(Heroicon::OutlinedPower)
                    ->action(function (NotificationChannel $record): void {
                        $record->update(['is_enabled' => ! $record->is_enabled]);
                        $this->resetTable();
                    }),
                Action::make('sendTest')
                    ->label('Send test')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->action(function (NotificationChannel $record): void {
                        $sent = app(NotificationDispatchService::class)->send(
                            $record,
                            'MTP Deploy test notification',
                            'If you can read this, this notification channel is configured correctly.',
                        );

                        Notification::make()
                            ->title($sent ? 'Test notification sent' : 'Test notification failed')
                            ->success($sent)
                            ->danger(! $sent)
                            ->send();
                    }),
                DeleteAction::make()
                    ->successNotificationTitle('Channel removed'),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function configFor(NotificationChannelType $channel, array $data): array
    {
        return match ($channel) {
            NotificationChannelType::Email => array_filter(['email' => $data['config']['email'] ?? null]),
            NotificationChannelType::Telegram => [
                'bot_token' => $data['config']['bot_token'] ?? null,
                'chat_id' => $data['config']['chat_id'] ?? null,
            ],
            NotificationChannelType::Discord, NotificationChannelType::Slack => [
                'webhook_url' => $data['config']['webhook_url'] ?? null,
            ],
        };
    }
}
