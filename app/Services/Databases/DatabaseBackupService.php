<?php

declare(strict_types=1);

namespace App\Services\Databases;

use App\Models\Database;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Real mysqldump/mysql-client backups, not a placeholder. Credentials are
 * never passed as CLI arguments (visible to any other process on the
 * machine via `ps`/Task Manager) - they go through a temporary
 * `--defaults-extra-file` option file instead, the standard secure pattern
 * for scripting the MySQL client tools, deleted immediately after use.
 */
class DatabaseBackupService
{
    public function backup(Database $database): string
    {
        File::ensureDirectoryExists(config('mtp.database_backups_path'));

        $filename = sprintf('%s_%s.sql', $database->name, now()->format('Y-m-d_His'));
        $path = rtrim(config('mtp.database_backups_path'), '/').'/'.$filename;

        $optionFile = $this->writeOptionFile();

        try {
            $process = new Process([
                config('mtp.mysqldump_path'),
                '--defaults-extra-file='.$optionFile,
                '--single-transaction',
                '--routines',
                $database->name,
            ]);
            $process->setTimeout(300);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('mysqldump failed: '.$process->getErrorOutput());
            }

            File::put($path, $process->getOutput());
        } finally {
            File::delete($optionFile);
        }

        return $path;
    }

    public function restore(Database $database, string $backupFilePath): void
    {
        if (! File::exists($backupFilePath)) {
            throw new RuntimeException("Backup file not found: {$backupFilePath}");
        }

        $optionFile = $this->writeOptionFile();

        try {
            $process = new Process([
                config('mtp.mysql_cli_path'),
                '--defaults-extra-file='.$optionFile,
                $database->name,
            ]);
            $process->setTimeout(300);
            $process->setInput(File::get($backupFilePath));
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Restore failed: '.$process->getErrorOutput());
            }
        } finally {
            File::delete($optionFile);
        }
    }

    /**
     * @return string path to the temporary option file - caller must delete it
     */
    private function writeOptionFile(): string
    {
        $connection = config('database.connections.mysql_admin');

        $path = tempnam(sys_get_temp_dir(), 'mtp-mysql-');

        File::put($path, implode(PHP_EOL, [
            '[client]',
            'host='.$connection['host'],
            'port='.$connection['port'],
            'user='.$connection['username'],
            'password='.$connection['password'],
            '',
        ]));

        return $path;
    }
}
