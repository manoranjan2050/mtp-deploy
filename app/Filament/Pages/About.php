<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\Updates\UpdateCheckerService;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class About extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedInformationCircle;

    protected static string|\UnitEnum|null $navigationGroup = 'Account';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.about';

    public function getCurrentCommit(): ?string
    {
        return app(UpdateCheckerService::class)->currentCommit();
    }

    public function isUpdateAvailable(): bool
    {
        return app(UpdateCheckerService::class)->isUpdateAvailable();
    }

    public function getRepoUrl(): string
    {
        return 'https://github.com/'.config('mtp.github_repo');
    }
}
