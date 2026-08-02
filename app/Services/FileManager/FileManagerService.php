<?php

declare(strict_types=1);

namespace App\Services\FileManager;

use App\DTOs\FileManager\FileEntryData;
use App\Models\Website;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use ZipArchive;

/**
 * Every operation here is scoped to a single website's `document_root` and
 * every path argument is treated as untrusted - this is a browser-facing file
 * manager, the single highest-risk surface for path traversal in the whole
 * app. Every resolved path is checked, after resolution, to still be inside
 * the website's root; syntactic ".."/absolute-path rejection happens first as
 * a fast fail, but the real guarantee is the post-resolution containment
 * check (`assertWithinRoot`), which also catches symlink escapes since
 * `realpath()` resolves symlinks. See docs/Security.md.
 */
class FileManagerService
{
    /**
     * Zip-bomb guard: an archive is rejected if its claimed uncompressed size
     * exceeds this cap, or if the uncompressed:compressed ratio is
     * implausibly high for real files (a ratio this extreme is the
     * signature of a crafted decompression bomb, not genuine content).
     */
    private const MAX_UNCOMPRESSED_BYTES = 512 * 1024 * 1024;

    private const MAX_COMPRESSION_RATIO = 100;

    private readonly string $root;

    public function __construct(Website $website)
    {
        $real = realpath($website->document_root);

        if ($real === false) {
            throw new FileManagerException("Website document root does not exist: {$website->document_root}");
        }

        $this->root = rtrim(str_replace('\\', '/', $real), '/');
    }

    /**
     * @return Collection<int, FileEntryData>
     */
    public function list(string $relativePath = ''): Collection
    {
        $dir = $this->resolveExistingDirectory($relativePath);

        return collect(File::files($dir))
            ->map(fn ($file) => new FileEntryData(
                name: $file->getFilename(),
                relativePath: $this->toRelative($file->getPathname()),
                isDirectory: false,
                sizeBytes: $file->getSize(),
                modifiedAt: $file->getMTime(),
            ))
            ->concat(collect(File::directories($dir))->map(fn (string $path) => new FileEntryData(
                name: basename($path),
                relativePath: $this->toRelative($path),
                isDirectory: true,
                sizeBytes: 0,
                modifiedAt: filemtime($path) ?: 0,
            )))
            ->sortBy([['isDirectory', 'desc'], ['name', 'asc']])
            ->values();
    }

    public function upload(string $relativeDirectory, UploadedFile $file): void
    {
        $dir = $this->resolveExistingDirectory($relativeDirectory);
        $filename = $this->assertSafeFilename($file->getClientOriginalName());

        // Reading the temp file's contents and writing them out (rather than
        // UploadedFile::move(), which is a rename-or-copy operation) avoids
        // Livewire's TemporaryUploadedFile failing to move across its own
        // temp-disk lifecycle - both a real HTTP upload and a Livewire
        // temporary upload always expose a readable getRealPath().
        File::put($dir.'/'.$filename, File::get($file->getRealPath()));
    }

    public function createDirectory(string $relativeDirectory, string $name): void
    {
        $dir = $this->resolveExistingDirectory($relativeDirectory);
        $name = $this->assertSafeFilename($name);

        File::makeDirectory($dir.'/'.$name);
    }

    public function rename(string $relativePath, string $newName): void
    {
        $path = $this->resolveExistingPath($relativePath);
        $newName = $this->assertSafeFilename($newName);

        File::move($path, dirname($path).'/'.$newName);
    }

    public function delete(string $relativePath): void
    {
        $path = $this->resolveExistingPath($relativePath);

        if (is_dir($path)) {
            File::deleteDirectory($path);
        } else {
            File::delete($path);
        }
    }

    public function readText(string $relativePath): string
    {
        return File::get($this->resolveExistingPath($relativePath));
    }

    public function writeText(string $relativePath, string $contents): void
    {
        $parent = $this->resolveExistingDirectory(dirname($relativePath) === '.' ? '' : dirname($relativePath));
        $filename = $this->assertSafeFilename(basename($relativePath));

        File::put($parent.'/'.$filename, $contents);
    }

    public function absolutePathFor(string $relativePath): string
    {
        return $this->resolveExistingPath($relativePath);
    }

    /**
     * @param  list<string>  $relativePaths  files/directories to include, relative to $relativeDirectory
     */
    public function zip(string $relativeDirectory, array $relativePaths, string $zipFilename): void
    {
        $dir = $this->resolveExistingDirectory($relativeDirectory);
        $zipFilename = $this->assertSafeFilename($zipFilename);

        $zip = new ZipArchive;
        $zipPath = $dir.'/'.$zipFilename;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new FileManagerException("Could not create zip file: {$zipFilename}");
        }

        foreach ($relativePaths as $entry) {
            $entryName = $this->assertSafeFilename($entry);
            $entryPath = $dir.'/'.$entryName;

            if (! File::exists($entryPath)) {
                continue;
            }

            if (is_dir($entryPath)) {
                $this->addDirectoryToZip($zip, $entryPath, $entryName);
            } else {
                $zip->addFile($entryPath, $entryName);
            }
        }

        $zip->close();
    }

    /**
     * Guards against "zip slip" - a malicious archive entry name containing
     * `../` that would otherwise write outside the intended extraction
     * directory. Every entry's target path is resolved and re-checked against
     * the extraction directory before being written, exactly like every other
     * path in this class.
     */
    public function unzip(string $relativePath): void
    {
        $zipPath = $this->resolveExistingPath($relativePath);
        $destination = dirname($zipPath);

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new FileManagerException("Could not open zip file: {$relativePath}");
        }

        $this->assertSafeToExtract($zip, $zipPath);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);

            if ($entryName === false || str_contains($entryName, '..') || str_starts_with($entryName, '/')) {
                continue;
            }

            $targetPath = str_replace('\\', '/', $destination.'/'.$entryName);

            if (! str_starts_with($targetPath, $destination.'/') && $targetPath !== $destination) {
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

    private function assertSafeToExtract(ZipArchive $zip, string $zipPath): void
    {
        $totalUncompressed = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);

            if ($stat !== false) {
                $totalUncompressed += $stat['size'];
            }
        }

        if ($totalUncompressed > self::MAX_UNCOMPRESSED_BYTES) {
            $zip->close();

            throw new FileManagerException('Archive exceeds the maximum allowed uncompressed size.');
        }

        $compressedSize = max(1, (int) filesize($zipPath));

        if ($totalUncompressed / $compressedSize > self::MAX_COMPRESSION_RATIO) {
            $zip->close();

            throw new FileManagerException('Archive rejected: compression ratio suggests a decompression bomb.');
        }
    }

    private function addDirectoryToZip(ZipArchive $zip, string $path, string $entryName): void
    {
        $zip->addEmptyDir($entryName);

        foreach (File::allFiles($path) as $file) {
            $relative = $entryName.'/'.ltrim(str_replace($path, '', $file->getPathname()), '/\\');
            $zip->addFile($file->getPathname(), str_replace('\\', '/', $relative));
        }
    }

    private function resolveExistingDirectory(string $relativePath): string
    {
        $path = $this->resolveExistingPath($relativePath);

        if (! is_dir($path)) {
            throw new FileManagerException("Not a directory: {$relativePath}");
        }

        return $path;
    }

    private function resolveExistingPath(string $relativePath): string
    {
        $this->assertSafeRelativePath($relativePath);

        $candidate = $relativePath === ''
            ? $this->root
            : $this->root.'/'.ltrim($relativePath, '/\\');

        $real = realpath($candidate);

        if ($real === false) {
            throw new FileManagerException("Path does not exist: {$relativePath}");
        }

        $real = str_replace('\\', '/', $real);

        $this->assertWithinRoot($real);

        return $real;
    }

    private function assertSafeRelativePath(string $path): void
    {
        if (str_contains($path, "\0")) {
            throw new FileManagerException('Invalid path.');
        }

        if (preg_match('#(^|[\\\\/])\.\.([\\\\/]|$)#', $path) === 1) {
            throw new FileManagerException('Path traversal is not allowed.');
        }

        if (preg_match('#^([a-zA-Z]:|[\\\\/])#', $path) === 1) {
            throw new FileManagerException('Absolute paths are not allowed.');
        }
    }

    private function assertSafeFilename(string $name): string
    {
        if ($name === '' || $name === '.' || $name === '..' || str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, "\0")) {
            throw new FileManagerException("Invalid filename: {$name}");
        }

        return $name;
    }

    private function assertWithinRoot(string $real): void
    {
        if ($real !== $this->root && ! str_starts_with($real.'/', $this->root.'/')) {
            throw new FileManagerException('Resolved path escapes the website document root.');
        }
    }

    private function toRelative(string $absolutePath): string
    {
        $normalized = str_replace('\\', '/', $absolutePath);

        return ltrim(substr($normalized, strlen($this->root)), '/');
    }
}
