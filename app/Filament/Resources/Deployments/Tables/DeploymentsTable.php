<?php

declare(strict_types=1);

namespace App\Filament\Resources\Deployments\Tables;

use App\Actions\Deployments\RollbackDeploymentAction;
use App\Enums\DeploymentStatus;
use App\Models\Deployment;
use App\Services\AiAssistant\AiAssistantService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DeploymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('website.domain')
                    ->label('Website')
                    ->searchable(),
                TextColumn::make('branch'),
                TextColumn::make('commit_sha')
                    ->label('Commit')
                    ->formatStateUsing(fn (?string $state): string => $state ? substr($state, 0, 7) : '—')
                    ->fontFamily('mono'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('triggered_by')
                    ->badge(),
                TextColumn::make('triggeredByUser.name')
                    ->label('By')
                    ->placeholder('—'),
                TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('finished_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(DeploymentStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('rollback')
                    ->label('Roll back to this')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('warning')
                    ->visible(fn (Deployment $record): bool => $record->status === DeploymentStatus::Success)
                    ->authorize(fn (Deployment $record): bool => auth()->user()->can('rollback', $record))
                    ->requiresConfirmation()
                    ->modalDescription('Deploys this exact commit again as a new deployment.')
                    ->action(function (Deployment $record): void {
                        $rollback = app(RollbackDeploymentAction::class)->handle($record, auth()->user());

                        Notification::make()
                            ->title($rollback->status === DeploymentStatus::RolledBack ? 'Rolled back' : 'Rollback failed')
                            ->success($rollback->status === DeploymentStatus::RolledBack)
                            ->danger($rollback->status !== DeploymentStatus::RolledBack)
                            ->send();
                    }),
                Action::make('explainWithAi')
                    ->label('Explain with AI')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->color('gray')
                    ->visible(fn (Deployment $record): bool => $record->status === DeploymentStatus::Failed)
                    ->authorize(fn (): bool => auth()->user()->can('use ai assistant'))
                    ->action(function (Deployment $record): void {
                        $result = app(AiAssistantService::class)->ask(
                            'You are a senior DevOps engineer helping triage a failed deployment. '
                            .'Explain briefly why it likely failed and suggest a concrete next step. Be concise.',
                            "Deployment log for {$record->website->domain} (branch {$record->branch}):\n\n{$record->log}",
                        );

                        activity('ai_assistant')
                            ->causedBy(auth()->user())
                            ->performedOn($record)
                            ->withProperties(['successful' => $result->successful])
                            ->log('explained a failed deployment');

                        Notification::make()
                            ->title($result->successful ? 'AI explanation' : 'AI Assistant unavailable')
                            ->body($result->successful ? $result->text : $result->error)
                            ->success($result->successful)
                            ->warning(! $result->successful)
                            ->persistent()
                            ->send();
                    }),
            ])
            ->toolbarActions([]);
    }
}
