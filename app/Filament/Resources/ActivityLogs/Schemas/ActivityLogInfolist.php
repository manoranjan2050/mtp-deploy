<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivityLogs\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ActivityLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('When')
                            ->dateTime(),
                        TextEntry::make('causer.name')
                            ->label('Causer')
                            ->placeholder('System'),
                        TextEntry::make('event')
                            ->badge(),
                        TextEntry::make('subject_type')
                            ->label('Subject type')
                            ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '-'),
                        TextEntry::make('subject_id')
                            ->label('Subject ID'),
                        TextEntry::make('log_name')
                            ->label('Log'),
                        TextEntry::make('description')
                            ->columnSpanFull(),
                    ]),
                Section::make('Property changes')
                    ->schema([
                        KeyValueEntry::make('properties.attributes')
                            ->label('New values'),
                        KeyValueEntry::make('properties.old')
                            ->label('Previous values'),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }
}
