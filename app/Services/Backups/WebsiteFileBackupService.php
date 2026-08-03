<?php

declare(strict_types=1);

namespace App\Services\Backups;

use App\Models\Website;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

/**
 * Real zip archives of a website's document root - not a placeholder. Lives
 * entirely separately from FileManagerService (Module 7): that class is
 * scoped *inside* one website's document root for browser-facing file
 * operations, whereas a backup necessarily operates from *outside* it (you
 * can't zip a directory from within itself), so this is deliberately its own
 * small service rather than a forced reuse.
 */
class WebsiteFileBackupService
{
    public function backup(Website $website): string
    {
        $root = config('mtp.website_backups_path').'/'.$website->domain;
        File::ensureDirectoryExists($root);

        $filename = sprintf('%s_%s_files.zip', $website->domain, now()->format('Y-m-d_His'));
        $path = "{$root}/{$filename}";

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Could not create backup archive: {$filename}");
        }

        $documentRoot = rtrim(str_replace('\\', '/', $website->document_root), '/');

        foreach (File::allFiles($documentRoot) as $file) {
            $relative = str_replace('\\', '/', ltrim(substr($file->getPathname(), strlen($documentRoot)), '/\\'));
            $zip->addFile($file->getPathname(), $relative);
        }

        $zip->close();

        return $path;
    }

    public function restore(Website $website, string $archivePath): void
    {
        if (! File::exists($archivePath)) {
            throw new RuntimeException("Backup archive not found: {$archivePath}");
        }

        $destination = rtrim(str_replace('\\', '/', $website->document_root), '/');
        File::ensureDirectoryExists($destination);

        $zip = new ZipArchive;

        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException("Could not open backup archive: {$archivePath}");
        }

        // Zip-slip guard, same principle as FileManagerService::unzip() - a
        // backup archive is trusted more than an arbitrary upload, but the
        // check is cheap and there is no reason to skip it.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);

            if ($entryName === false || str_contains($entryName, '..') || str_starts_with($entryName, '/')) {
                continue;
            }

            $targetPath = str_replace('\\', '/', $destination.'/'.$entryName);

            if (! str_starts_with($targetPath, $destination.'/')) {
                continue;
            }

            if (str_ends_with($entryName, '/')) {
                File::ensureDirectoryExists($targetPath);

                continue;
            }

            File::ensureDirectoryExists(dirname($targetPath));
            $zip->extractTo($destination, $entryName);
        }

        $zip->close();
    }
}
