<?php

declare(strict_types=1);

namespace App\Actions\Backups;

use App\Enums\BackupType;
use App\Models\Backup;
use Illuminate\Support\Facades\File;

class DeleteBackupAction
{
    public function handle(Backup $backup): void
    {
        if ($backup->type !== BackupType::GitSnapshot && $backup->disk_path) {
            File::delete($backup->disk_path);
        }

        $backup->delete();
    }
}
