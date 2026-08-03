<?php

declare(strict_types=1);

namespace App\Services\Logs;

use SplFileObject;

/**
 * Real file I/O against real log files - not a placeholder, and unlike most
 * services in this project, needs no special system binary (crontab,
 * supervisorctl, nginx) to be meaningful, so it's fully exercised on any OS
 * including this Windows dev box. `tail()`/`search()` both cap how much they
 * read so a multi-gigabyte log file can't exhaust memory or hang a request.
 */
class LogFileReaderService
{
    public function exists(string $path): bool
    {
        return is_file($path) && is_readable($path);
    }

    public function tail(string $path, int $maxLines = 200): string
    {
        $this->assertReadable($path);

        $file = new SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $startLine = max(0, $totalLines - $maxLines);
        $file->seek($startLine);

        $lines = [];

        while (! $file->eof()) {
            $line = $file->fgets();

            if ($line === false) {
                break;
            }

            $lines[] = rtrim($line, "\r\n");
        }

        // A normally newline-terminated file (the overwhelming common case)
        // otherwise leaves one phantom empty "line" at the end, since
        // SplFileObject counts the empty string after the final \n as its
        // own line - the same convention `wc -l`/`tail` themselves use.
        if ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        return implode("\n", $lines);
    }

    public function search(string $path, string $needle, int $maxMatches = 200): string
    {
        $this->assertReadable($path);

        if (trim($needle) === '') {
            return $this->tail($path, $maxMatches);
        }

        $matches = [];
        $file = new SplFileObject($path, 'r');

        while (! $file->eof() && count($matches) < $maxMatches) {
            $line = $file->fgets();

            if ($line !== false && stripos($line, $needle) !== false) {
                $matches[] = rtrim($line, "\r\n");
            }
        }

        return implode("\n", $matches);
    }

    public function sizeInBytes(string $path): ?int
    {
        return $this->exists($path) ? filesize($path) : null;
    }

    private function assertReadable(string $path): void
    {
        if (! $this->exists($path)) {
            throw new LogFileNotFoundException("Log file not found or not readable: {$path}");
        }
    }
}
