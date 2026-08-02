<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                    ->helperText('Leave blank to keep the current password.'),
                CheckboxList::make('roles')
                    ->relationship('roles', 'name')
                    ->options(fn (): array => Role::query()->pluck('name', 'name')->all())
                    ->columns(2),
                Toggle::make('is_active')
                    ->label('Active')
                    ->helperText('Suspended users cannot sign in.')
                    ->default(true),
                DateTimePicker::make('last_login_at')
                    ->label('Last login')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('last_login_ip')
                    ->label('Last login IP')
                    ->disabled()
                    ->dehydrated(false),
            ]);
    }
}
