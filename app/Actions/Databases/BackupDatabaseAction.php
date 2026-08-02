<?php

declare(strict_types=1);

namespace App\Actions\Databases;

use App\Models\Database;
use App\Services\Databases\DatabaseBackupService;

class BackupDatabaseAction
{
    public function __construct(
        private readonly DatabaseBackupService $backups,
    ) {}

    public function handle(Database $database): string
    {
        $path = $this->backups->backup($database);

        activity('database')
            ->causedBy(auth()->user())
            ->performedOn($database)
            ->withProperties(['path' => $path])
            ->log('backed up database');

        return $path;
    }
}
