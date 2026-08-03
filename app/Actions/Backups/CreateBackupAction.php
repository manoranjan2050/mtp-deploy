<?php

declare(strict_types=1);

namespace App\Actions\Backups;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Models\Backup;
use App\Models\User;
use App\Models\Website;
use App\Services\Backups\GitBackupService;
use App\Services\Backups\WebsiteFileBackupService;
use App\Services\Databases\DatabaseBackupService;
use Illuminate\Support\Facades\File;
use Throwable;
use ZipArchive;

class CreateBackupAction
{
    public function __construct(
        private readonly WebsiteFileBackupService $files,
        private readonly DatabaseBackupService $database,
        private readonly GitBackupService $git,
    ) {}

    public function handle(Website $website, BackupType $type, ?User $user = null): Backup
    {
        $backup = Backup::query()->create([
            'website_id' => $website->id,
            'created_by' => $user?->id,
            'type' => $type,
            'status' => BackupStatus::Running,
            'started_at' => now(),
        ]);

        try {
            $diskPath = match ($type) {
                BackupType::Files => $this->files->backup($website),
                BackupType::Database => $this->backupDatabases($website),
                BackupType::Full => $this->backupFull($website),
                BackupType::GitSnapshot => $this->gitDiskPath($website),
            };

            $backup->update([
                'disk_path' => $diskPath,
                'size_bytes' => File::exists($diskPath) ? File::size($diskPath) : null,
                'status' => BackupStatus::Success,
                'completed_at' => now(),
            ]);

            activity('backup')
                ->causedBy($user)
                ->performedOn($website)
                ->withProperties(['type' => $type->value])
                ->log('created backup');

            return $backup->fresh();
        } catch (Throwable $exception) {
            $backup->update([
                'status' => BackupStatus::Failed,
                'error' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            throw $exception;
        }
    }

    /**
     * A website can have more than one database attached - all of their
     * dumps are bundled into a single zip so one Backup row still maps to
     * one downloadable/restorable artifact.
     */
    private function backupDatabases(Website $website): string
    {
        $databases = $website->databases()->get();

        if ($databases->isEmpty()) {
            throw new \RuntimeException('This website has no databases to back up.');
        }

        $dumpPaths = $databases->map(fn ($db) => $this->database->backup($db));

        return $this->zipPaths($website, $dumpPaths->all(), 'database');
    }

    private function backupFull(Website $website): string
    {
        $filesZip = $this->files->backup($website);
        $paths = [$filesZip];

        foreach ($website->databases()->get() as $database) {
            $paths[] = $this->database->backup($database);
        }

        return $this->zipPaths($website, $paths, 'full');
    }

    /**
     * @param  list<string>  $paths
     */
    private function zipPaths(Website $website, array $paths, string $suffix): string
    {
        $root = config('mtp.website_backups_path').'/'.$website->domain;
        File::ensureDirectoryExists($root);

        $filename = sprintf('%s_%s_%s.zip', $website->domain, now()->format('Y-m-d_His'), $suffix);
        $finalPath = "{$root}/{$filename}";

        $zip = new ZipArchive;
        $zip->open($finalPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($paths as $path) {
            $zip->addFile($path, basename($path));
        }

        $zip->close();

        foreach ($paths as $path) {
            File::delete($path);
        }

        return $finalPath;
    }

    /**
     * Git snapshots aren't a single file on disk the way zip backups are -
     * `disk_path` instead stores "{bare repo path}#{commit sha}" so
     * RestoreBackupAction can parse both pieces back out. See
     * GitBackupService for the real git operations behind this.
     */
    private function gitDiskPath(Website $website): string
    {
        $sha = $this->git->snapshot($website);
        $gitDir = config('mtp.git_backups_path').'/'.$website->domain.'.git';

        return "{$gitDir}#{$sha}";
    }
}
