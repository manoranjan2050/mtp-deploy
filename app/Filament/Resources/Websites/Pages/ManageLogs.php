<?php

declare(strict_types=1);

namespace App\Filament\Resources\Websites\Pages;

use App\Filament\Resources\Websites\WebsiteResource;
use App\Models\Website;
use App\Services\Logs\LogFileReaderService;
use App\Services\Logs\WebsiteLogSourceResolver;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;

class ManageLogs extends Page
{
    use InteractsWithRecord;

    protected static string $resource = WebsiteResource::class;

    protected string $view = 'filament.resources.websites.pages.manage-logs';

    public ?string $activeSource = null;

    #[Validate('nullable|string|max:255')]
    public string $query = '';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(auth()->user()->can('view', $this->getRecord()), 403);

        $sources = $this->sources();
        $this->activeSource = array_key_first($sources);
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function sources(): array
    {
        /** @var Website $website */
        $website = $this->getRecord();

        return app(WebsiteLogSourceResolver::class)->sources($website);
    }

    public function selectSource(string $label): void
    {
        if (array_key_exists($label, $this->sources())) {
            $this->activeSource = $label;
        }
    }

    #[Computed]
    public function logContent(): string
    {
        $sources = $this->sources();
        $path = $sources[$this->activeSource] ?? null;

        if ($path === null) {
            return '';
        }

        $reader = app(LogFileReaderService::class);

        if (! $reader->exists($path)) {
            return "(not found on this server: {$path})";
        }

        return trim($this->query) === ''
            ? $reader->tail($path, 300)
            : $reader->search($path, $this->query, 300);
    }

    public function refresh(): void
    {
        unset($this->logContent);
    }
}
