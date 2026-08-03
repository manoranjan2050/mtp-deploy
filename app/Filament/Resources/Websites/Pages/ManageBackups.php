<?php

declare(strict_types=1);

namespace App\Filament\Resources\Websites\Pages;

use App\Actions\Backups\CreateBackupAction;
use App\Actions\Backups\DeleteBackupAction;
use App\Actions\Backups\RestoreBackupAction;
use App\Enums\BackupType;
use App\Filament\Resources\Websites\WebsiteResource;
use App\Models\Backup;
use App\Models\User;
use App\Models\Website;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Throwable;

class ManageBackups extends Page
{
    use InteractsWithRecord;

    protected static string $resource = WebsiteResource::class;

    protected string $view = 'filament.resources.websites.pages.manage-backups';

    public bool $backupsEnabled = false;

    public int $retentionCount = 7;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(auth()->user()->can('update', $this->getRecord()), 403);

        /** @var Website $website */
        $website = $this->getRecord();
        $this->backupsEnabled = $website->backups_enabled;
        $this->retentionCount = $website->backup_retention_count;
    }

    /**
     * @return Collection<int, Backup>
     */
    #[Computed]
    public function backups(): Collection
    {
        /** @var Website $website */
        $website = $this->getRecord();

        return $website->backups()->get();
    }

    public function saveSchedule(): void
    {
        $this->validate(['retentionCount' => 'required|integer|min:1|max:365']);

        $this->getRecord()->update([
            'backups_enabled' => $this->backupsEnabled,
            'backup_retention_count' => $this->retentionCount,
        ]);

        Notification::make()->title('Backup schedule saved')->success()->send();
    }

    public function createBackup(string $type): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            app(CreateBackupAction::class)->handle($this->getRecord(), BackupType::from($type), $user);

            Notification::make()->title('Backup created')->success()->send();
        } catch (Throwable $exception) {
            Notification::make()->title('Backup failed')->body($exception->getMessage())->danger()->send();
        }

        unset($this->backups);
    }

    public function restoreBackup(int $backupId): void
    {
        $backup = Backup::query()->where('website_id', $this->getRecord()->id)->findOrFail($backupId);

        try {
            app(RestoreBackupAction::class)->handle($backup, auth()->user());

            Notification::make()->title('Backup restored')->success()->send();
        } catch (Throwable $exception) {
            Notification::make()->title('Restore failed')->body($exception->getMessage())->danger()->send();
        }
    }

    public function deleteBackup(int $backupId): void
    {
        $backup = Backup::query()->where('website_id', $this->getRecord()->id)->findOrFail($backupId);

        app(DeleteBackupAction::class)->handle($backup);

        unset($this->backups);

        Notification::make()->title('Backup deleted')->success()->send();
    }
}
