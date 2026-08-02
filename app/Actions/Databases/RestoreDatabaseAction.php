<?php

declare(strict_types=1);

namespace App\Actions\Databases;

use App\Models\Database;
use App\Services\Databases\DatabaseBackupService;

class RestoreDatabaseAction
{
    public function __construct(
        private readonly DatabaseBackupService $backups,
    ) {}

    public function handle(Database $database, string $backupFilePath): void
    {
        $this->backups->restore($database, $backupFilePath);

        activity('database')
            ->causedBy(auth()->user())
            ->performedOn($database)
            ->withProperties(['path' => $backupFilePath])
            ->log('restored database from backup');
    }
}
