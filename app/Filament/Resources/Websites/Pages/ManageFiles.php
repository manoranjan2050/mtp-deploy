<?php

declare(strict_types=1);

namespace App\Filament\Resources\Websites\Pages;

use App\Actions\FileManager\CreateDirectoryAction;
use App\Actions\FileManager\DeleteFileAction;
use App\Actions\FileManager\RenameFileAction;
use App\Actions\FileManager\UnzipFileAction;
use App\Actions\FileManager\UploadFileAction;
use App\Actions\FileManager\WriteFileAction;
use App\Actions\FileManager\ZipFilesAction;
use App\DTOs\FileManager\FileEntryData;
use App\Filament\Resources\Websites\WebsiteResource;
use App\Models\Website;
use App\Services\FileManager\FileManagerException;
use App\Services\FileManager\FileManagerService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManageFiles extends Page
{
    use InteractsWithRecord;
    use WithFileUploads;

    protected static string $resource = WebsiteResource::class;

    protected string $view = 'filament.resources.websites.pages.manage-files';

    public string $currentDirectory = '';

    #[Validate('required|string|max:255')]
    public string $newFolderName = '';

    public mixed $newUpload = null;

    public ?string $editingPath = null;

    public string $editingContents = '';

    public ?string $renamingPath = null;

    public string $renamingName = '';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(auth()->user()->can('manageFiles', $this->getRecord()), 403);

        $this->assertCurrentDirectoryIsListable();
    }

    /**
     * @return Collection<int, FileEntryData>
     */
    #[Computed]
    public function entries(): Collection
    {
        try {
            return (new FileManagerService($this->getRecord()))->list($this->currentDirectory);
        } catch (FileManagerException) {
            return collect();
        }
    }

    public function getTitle(): string|Htmlable
    {
        /** @var Website $website */
        $website = $this->getRecord();

        return "Files — {$website->domain}";
    }

    /**
     * @return list<array{label: string, path: string}>
     */
    public function getBreadcrumbTrail(): array
    {
        $trail = [['label' => '/', 'path' => '']];

        if ($this->currentDirectory === '') {
            return $trail;
        }

        $accumulated = [];

        foreach (explode('/', $this->currentDirectory) as $segment) {
            $accumulated[] = $segment;
            $trail[] = ['label' => $segment, 'path' => implode('/', $accumulated)];
        }

        return $trail;
    }

    public function navigateTo(string $path): void
    {
        $this->currentDirectory = $path;
        $this->cancelEditing();
        $this->cancelRenaming();
        $this->assertCurrentDirectoryIsListable();
    }

    public function navigateUp(): void
    {
        $parent = dirname($this->currentDirectory);
        $this->navigateTo($parent === '.' ? '' : $parent);
    }

    public function createFolder(): void
    {
        $this->validate();

        try {
            app(CreateDirectoryAction::class)->handle($this->getRecord(), $this->currentDirectory, $this->newFolderName);

            Notification::make()->title('Folder created')->success()->send();
        } catch (FileManagerException $exception) {
            Notification::make()->title('Could not create folder')->body($exception->getMessage())->danger()->send();
        }

        $this->newFolderName = '';
        unset($this->entries);
    }

    public function upload(): void
    {
        $this->validate(['newUpload' => 'required|file']);

        try {
            app(UploadFileAction::class)->handle($this->getRecord(), $this->currentDirectory, $this->newUpload);

            Notification::make()->title('File uploaded')->success()->send();
        } catch (FileManagerException $exception) {
            Notification::make()->title('Upload failed')->body($exception->getMessage())->danger()->send();
        }

        $this->newUpload = null;
        unset($this->entries);
    }

    public function startRenaming(string $relativePath): void
    {
        $this->renamingPath = $relativePath;
        $this->renamingName = basename($relativePath);
    }

    public function cancelRenaming(): void
    {
        $this->renamingPath = null;
        $this->renamingName = '';
    }

    public function confirmRename(): void
    {
        $this->validate(['renamingName' => 'required|string|max:255']);

        try {
            app(RenameFileAction::class)->handle($this->getRecord(), $this->renamingPath, $this->renamingName);

            Notification::make()->title('Renamed')->success()->send();
        } catch (FileManagerException $exception) {
            Notification::make()->title('Rename failed')->body($exception->getMessage())->danger()->send();
        }

        $this->cancelRenaming();
        unset($this->entries);
    }

    public function delete(string $relativePath): void
    {
        try {
            app(DeleteFileAction::class)->handle($this->getRecord(), $relativePath);

            Notification::make()->title('Deleted')->success()->send();
        } catch (FileManagerException $exception) {
            Notification::make()->title('Delete failed')->body($exception->getMessage())->danger()->send();
        }

        unset($this->entries);
    }

    public function startEditing(string $relativePath): void
    {
        try {
            $this->editingContents = (new FileManagerService($this->getRecord()))->readText($relativePath);
            $this->editingPath = $relativePath;
        } catch (FileManagerException $exception) {
            Notification::make()->title('Could not open file')->body($exception->getMessage())->danger()->send();
        }
    }

    public function cancelEditing(): void
    {
        $this->editingPath = null;
        $this->editingContents = '';
    }

    public function saveEditing(): void
    {
        try {
            app(WriteFileAction::class)->handle($this->getRecord(), $this->editingPath, $this->editingContents);

            Notification::make()->title('File saved')->success()->send();
        } catch (FileManagerException $exception) {
            Notification::make()->title('Save failed')->body($exception->getMessage())->danger()->send();
        }

        $this->cancelEditing();
        unset($this->entries);
    }

    public function zipItem(string $relativePath): void
    {
        $directory = dirname($relativePath) === '.' ? '' : dirname($relativePath);
        $name = basename($relativePath);

        try {
            app(ZipFilesAction::class)->handle($this->getRecord(), $directory, [$name], $name.'.zip');

            Notification::make()->title('Zip archive created')->success()->send();
        } catch (FileManagerException $exception) {
            Notification::make()->title('Zip failed')->body($exception->getMessage())->danger()->send();
        }

        unset($this->entries);
    }

    public function unzipItem(string $relativePath): void
    {
        try {
            app(UnzipFileAction::class)->handle($this->getRecord(), $relativePath);

            Notification::make()->title('Archive extracted')->success()->send();
        } catch (FileManagerException $exception) {
            Notification::make()->title('Extraction failed')->body($exception->getMessage())->danger()->send();
        }

        unset($this->entries);
    }

    public function download(string $relativePath): ?StreamedResponse
    {
        try {
            $absolutePath = (new FileManagerService($this->getRecord()))->absolutePathFor($relativePath);
        } catch (FileManagerException $exception) {
            Notification::make()->title('Download failed')->body($exception->getMessage())->danger()->send();

            return null;
        }

        return response()->download($absolutePath, basename($relativePath));
    }

    private function assertCurrentDirectoryIsListable(): void
    {
        try {
            (new FileManagerService($this->getRecord()))->list($this->currentDirectory);
        } catch (FileManagerException) {
            $this->currentDirectory = '';
        }
    }
}
