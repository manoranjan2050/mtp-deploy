<?php

declare(strict_types=1);

namespace App\Actions\Backups;

use App\Enums\BackupType;
use App\Models\Backup;
use App\Models\User;
use App\Services\Backups\GitBackupService;
use App\Services\Backups\WebsiteFileBackupService;
use App\Services\Databases\DatabaseBackupService;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class RestoreBackupAction
{
    public function __construct(
        private readonly WebsiteFileBackupService $files,
        private readonly DatabaseBackupService $database,
        private readonly GitBackupService $git,
    ) {}

    public function handle(Backup $backup, ?User $user = null): void
    {
        $website = $backup->website;

        match ($backup->type) {
            BackupType::Files => $this->files->restore($website, $backup->disk_path),
            BackupType::Database => $this->restoreDatabasesFromZip($backup),
            BackupType::Full => $this->restoreFullFromZip($backup),
            BackupType::GitSnapshot => $this->restoreGit($backup),
        };

        activity('backup')
            ->causedBy($user)
            ->performedOn($website)
            ->withProperties(['type' => $backup->type->value, 'backup_id' => $backup->id])
            ->log('restored backup');
    }

    private function restoreDatabasesFromZip(Backup $backup): void
    {
        $dumps = $this->extractDumpsToTemp($backup->disk_path);
        $databases = $backup->website->databases()->get()->keyBy(fn ($db) => $db->name);

        foreach ($dumps as $dumpPath) {
            $databaseName = preg_replace('/_\d{4}-\d{2}-\d{2}_\d{6}\.sql$/', '', basename($dumpPath));
            $database = $databases->get($databaseName);

            if ($database !== null) {
                $this->database->restore($database, $dumpPath);
            }
        }

        File::deleteDirectory(dirname($dumps[0] ?? ''));
    }

    private function restoreFullFromZip(Backup $backup): void
    {
        $tempDir = sys_get_temp_dir().'/mtp-restore-'.uniqid();
        File::ensureDirectoryExists($tempDir);

        $zip = new ZipArchive;
        $zip->open($backup->disk_path);
        $zip->extractTo($tempDir);
        $zip->close();

        $filesArchive = collect(File::files($tempDir))->first(fn ($file) => str_ends_with($file->getFilename(), '_files.zip'));

        if ($filesArchive !== null) {
            $this->files->restore($backup->website, $filesArchive->getPathname());
        }

        $databases = $backup->website->databases()->get()->keyBy(fn ($db) => $db->name);

        foreach (File::files($tempDir) as $file) {
            if (! str_ends_with($file->getFilename(), '.sql')) {
                continue;
            }

            $databaseName = preg_replace('/_\d{4}-\d{2}-\d{2}_\d{6}\.sql$/', '', $file->getFilename());
            $database = $databases->get($databaseName);

            if ($database !== null) {
                $this->database->restore($database, $file->getPathname());
            }
        }

        File::deleteDirectory($tempDir);
    }

    private function restoreGit(Backup $backup): void
    {
        [, $sha] = array_pad(explode('#', $backup->disk_path, 2), 2, null);

        if ($sha === null) {
            throw new RuntimeException('Malformed git backup reference.');
        }

        $this->git->restore($backup->website, $sha);
    }

    /**
     * @return list<string>
     */
    private function extractDumpsToTemp(string $zipPath): array
    {
        $tempDir = sys_get_temp_dir().'/mtp-restore-'.uniqid();
        File::ensureDirectoryExists($tempDir);

        $zip = new ZipArchive;
        $zip->open($zipPath);
        $zip->extractTo($tempDir);
        $zip->close();

        return collect(File::files($tempDir))->map(fn ($file) => $file->getPathname())->all();
    }
}
