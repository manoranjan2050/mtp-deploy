<?php

declare(strict_types=1);

namespace App\Filament\Pages\Profile;

use App\Actions\Auth\RevokeSessionAction;
use App\Models\Session as SessionModel;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class Sessions extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.profile.sessions';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static string|\UnitEnum|null $navigationGroup = 'Account';

    protected static ?int $navigationSort = 20;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn (): Builder => SessionModel::query()
                    ->where('user_id', auth()->id())
                    ->orderByDesc('last_activity')
            )
            ->columns([
                TextColumn::make('ip_address')
                    ->label('IP address'),
                TextColumn::make('user_agent')
                    ->label('Device')
                    ->limit(60),
                TextColumn::make('last_activity')
                    ->label('Last active')
                    ->formatStateUsing(fn (int $state): string => Carbon::createFromTimestamp($state)->diffForHumans()),
                IconColumn::make('id')
                    ->label('This device')
                    ->boolean()
                    ->state(fn (SessionModel $record): bool => $record->id === session()->getId()),
            ])
            ->recordActions([
                Action::make('logout')
                    ->label('Log out')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (SessionModel $record): bool => $record->id !== session()->getId())
                    ->action(function (SessionModel $record): void {
                        /** @var User $user */
                        $user = auth()->user();

                        app(RevokeSessionAction::class)->handle($user, $record->id);

                        Notification::make()->title('Session revoked')->success()->send();

                        $this->resetTable();
                    }),
            ])
            ->headerActions([
                Action::make('logoutOthers')
                    ->label('Log out other devices')
                    ->color('danger')
                    ->icon(Heroicon::OutlinedShieldExclamation)
                    ->requiresConfirmation()
                    ->modalDescription('This will sign you out on every other device.')
                    ->action(function (): void {
                        /** @var User $user */
                        $user = auth()->user();

                        app(RevokeSessionAction::class)->handleOthers($user, session()->getId());

                        Notification::make()->title('Other sessions revoked')->success()->send();

                        $this->resetTable();
                    }),
            ]);
    }
}
