<?php

declare(strict_types=1);

namespace App\Filament\Pages\Profile;

use App\Enums\WebhookEvent;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\CheckboxList;
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
use Illuminate\Support\Str;

class WebhookEndpoints extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.profile.webhook-endpoints';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|\UnitEnum|null $navigationGroup = 'Account';

    protected static ?int $navigationSort = 23;

    protected static ?string $title = 'Webhook Endpoints';

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        return $table
            ->query(fn (): Builder => $user->webhookEndpoints()->getQuery())
            ->columns([
                TextColumn::make('url')
                    ->limit(50),
                TextColumn::make('events')
                    ->formatStateUsing(fn (WebhookEndpoint $record): string => collect($record->events)
                        ->map(fn (string $event): string => WebhookEvent::from($event)->label())
                        ->implode(', ')),
                IconColumn::make('is_enabled')
                    ->label('Enabled')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime(),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Add endpoint')
                    ->icon(Heroicon::OutlinedPlus)
                    ->schema([
                        TextInput::make('url')
                            ->label('Endpoint URL')
                            ->url()
                            ->required(),
                        CheckboxList::make('events')
                            ->label('Events')
                            ->options(WebhookEvent::options())
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        /** @var User $user */
                        $user = auth()->user();
                        $secret = Str::random(40);

                        $user->webhookEndpoints()->create([
                            'url' => $data['url'],
                            'secret' => $secret,
                            'events' => $data['events'],
                        ]);

                        Notification::make()
                            ->title('Endpoint added')
                            ->body("Signing secret (copy it now, it won't be shown again):\n{$secret}")
                            ->success()
                            ->persistent()
                            ->send();

                        $this->resetTable();
                    }),
            ])
            ->recordActions([
                Action::make('toggle')
                    ->label(fn (WebhookEndpoint $record): string => $record->is_enabled ? 'Disable' : 'Enable')
                    ->icon(Heroicon::OutlinedPower)
                    ->action(function (WebhookEndpoint $record): void {
                        $record->update(['is_enabled' => ! $record->is_enabled]);
                        $this->resetTable();
                    }),
                DeleteAction::make()
                    ->successNotificationTitle('Endpoint removed'),
            ]);
    }
}
