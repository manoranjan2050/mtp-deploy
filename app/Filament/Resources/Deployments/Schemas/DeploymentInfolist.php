<?php

declare(strict_types=1);

namespace App\Filament\Resources\Deployments\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DeploymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('website.domain')
                            ->label('Website'),
                        TextEntry::make('branch'),
                        TextEntry::make('commit_sha')
                            ->label('Commit')
                            ->fontFamily('mono')
                            ->placeholder('—'),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('triggered_by')
                            ->badge(),
                        TextEntry::make('triggeredByUser.name')
                            ->label('Triggered by')
                            ->placeholder('System / webhook'),
                        TextEntry::make('started_at')
                            ->dateTime(),
                        TextEntry::make('finished_at')
                            ->dateTime(),
                    ]),
                Section::make('Laravel deployment steps')
                    ->visible(fn ($record): bool => $record->steps()->exists())
                    ->schema([
                        RepeatableEntry::make('steps')
                            ->label('')
                            ->schema([
                                TextEntry::make('name'),
                                TextEntry::make('status')
                                    ->badge(),
                                TextEntry::make('output')
                                    ->label('Output')
                                    ->fontFamily('mono')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                    ]),
                Section::make('Log')
                    ->schema([
                        TextEntry::make('log')
                            ->label('')
                            ->fontFamily('mono')
                            ->placeholder('No output.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
