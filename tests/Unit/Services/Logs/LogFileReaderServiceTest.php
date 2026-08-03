<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Logs;

use App\Services\Logs\LogFileNotFoundException;
use App\Services\Logs\LogFileReaderService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Real file I/O against a real log file - unlike most of this project's
 * services, this needs no special system binary to be meaningful, so it's
 * fully exercised on any OS, including this Windows dev box.
 */
class LogFileReaderServiceTest extends TestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logPath = sys_get_temp_dir().'/mtp-log-test-'.uniqid().'.log';
    }

    protected function tearDown(): void
    {
        File::delete($this->logPath);

        parent::tearDown();
    }

    public function test_it_reports_whether_a_real_file_exists(): void
    {
        $service = app(LogFileReaderService::class);

        $this->assertFalse($service->exists($this->logPath));

        File::put($this->logPath, "line one\n");

        $this->assertTrue($service->exists($this->logPath));
    }

    public function test_tail_returns_only_the_last_n_lines_in_order(): void
    {
        $lines = collect(range(1, 500))->map(fn (int $n): string => "log line {$n}")->implode("\n");
        File::put($this->logPath, $lines."\n");

        $tail = app(LogFileReaderService::class)->tail($this->logPath, 10);
        $tailLines = explode("\n", $tail);

        $this->assertCount(10, $tailLines);
        $this->assertSame('log line 491', $tailLines[0]);
        $this->assertSame('log line 500', $tailLines[9]);
    }

    public function test_tail_on_a_short_file_returns_every_line(): void
    {
        File::put($this->logPath, "one\ntwo\nthree\n");

        $tail = app(LogFileReaderService::class)->tail($this->logPath, 200);

        $this->assertSame("one\ntwo\nthree", $tail);
    }

    public function test_search_returns_only_matching_lines_case_insensitively(): void
    {
        File::put($this->logPath, "INFO: all good\nERROR: something broke\nINFO: still fine\nERROR: broke again\n");

        $matches = app(LogFileReaderService::class)->search($this->logPath, 'error');
        $matchLines = explode("\n", $matches);

        $this->assertCount(2, $matchLines);
        $this->assertStringContainsString('something broke', $matchLines[0]);
        $this->assertStringContainsString('broke again', $matchLines[1]);
    }

    public function test_search_with_an_empty_query_falls_back_to_tail(): void
    {
        File::put($this->logPath, "a\nb\nc\n");

        $result = app(LogFileReaderService::class)->search($this->logPath, '   ');

        $this->assertSame("a\nb\nc", $result);
    }

    public function test_reading_a_missing_file_throws(): void
    {
        $this->expectException(LogFileNotFoundException::class);

        app(LogFileReaderService::class)->tail($this->logPath);
    }

    public function test_size_in_bytes_reflects_the_real_file_size(): void
    {
        File::put($this->logPath, str_repeat('a', 1234));

        $this->assertSame(1234, app(LogFileReaderService::class)->sizeInBytes($this->logPath));
    }
}
