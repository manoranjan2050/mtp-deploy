<?php

declare(strict_types=1);

namespace App\Filament\Pages\Profile;

use App\Actions\Auth\CreateApiTokenAction;
use App\Enums\ApiTokenAbility;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ApiTokens extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.profile.api-tokens';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = 'Account';

    protected static ?int $navigationSort = 21;

    protected static ?string $title = 'API Tokens';

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        return $table
            ->query(fn (): Builder => $user->tokens()->getQuery())
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('abilities')
                    ->badge()
                    ->formatStateUsing(fn (array $state): array => $state),
                TextColumn::make('last_used_at')
                    ->dateTime()
                    ->placeholder('Never'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime(),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Create token')
                    ->icon(Heroicon::OutlinedPlus)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        CheckboxList::make('abilities')
                            ->options(ApiTokenAbility::options())
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        /** @var User $user */
                        $user = auth()->user();

                        $token = app(CreateApiTokenAction::class)->handle($user, $data['name'], $data['abilities']);

                        Notification::make()
                            ->title('Token created')
                            ->body("Copy it now, it won't be shown again:\n".$token->plainTextToken)
                            ->success()
                            ->persistent()
                            ->send();

                        $this->resetTable();
                    }),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Revoke')
                    ->modalHeading('Revoke API token')
                    ->successNotificationTitle('Token revoked'),
            ]);
    }
}
