<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Backups\CreateBackupAction;
use App\Actions\Backups\DeleteBackupAction;
use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Models\Website;
use Illuminate\Console\Command;
use Throwable;

class RunScheduledBackups extends Command
{
    protected $signature = 'app:run-scheduled-backups';

    protected $description = 'Create a full backup for every website with backups enabled, then prune old ones past its retention count';

    public function handle(CreateBackupAction $create, DeleteBackupAction $delete): int
    {
        $websites = Website::query()->where('backups_enabled', true)->get();

        foreach ($websites as $website) {
            $this->info("Backing up {$website->domain}...");

            try {
                $create->handle($website, BackupType::Full);
                $this->info('  succeeded.');
            } catch (Throwable $exception) {
                $this->error("  failed: {$exception->getMessage()}");
            }

            $stale = $website->backups()
                ->where('status', BackupStatus::Success)
                ->skip($website->backup_retention_count)
                ->take(100000)
                ->get();

            foreach ($stale as $backup) {
                $delete->handle($backup);
            }
        }

        if ($websites->isEmpty()) {
            $this->info('No websites have backups enabled.');
        }

        return self::SUCCESS;
    }
}
