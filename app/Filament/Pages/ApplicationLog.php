<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\Logs\LogFileReaderService;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;

class ApplicationLog extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 18;

    protected string $view = 'filament.pages.application-log';

    #[Validate('nullable|string|max:255')]
    public string $query = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view application logs') === true;
    }

    public function mount(): void
    {
        abort_unless(auth()->user()->can('view application logs'), 403);
    }

    public function path(): string
    {
        return storage_path('logs/laravel.log');
    }

    #[Computed]
    public function logContent(): string
    {
        $reader = app(LogFileReaderService::class);
        $path = $this->path();

        if (! $reader->exists($path)) {
            return "(not found: {$path})";
        }

        return trim($this->query) === ''
            ? $reader->tail($path, 300)
            : $reader->search($path, $this->query, 300);
    }

    public function sizeLabel(): string
    {
        $reader = app(LogFileReaderService::class);
        $bytes = $reader->sizeInBytes($this->path());

        if ($bytes === null) {
            return 'not found';
        }

        return number_format($bytes / 1024, 1).' KB';
    }

    public function refresh(): void
    {
        unset($this->logContent);
    }
}
