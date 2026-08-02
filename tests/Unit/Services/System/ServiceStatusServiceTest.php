<?php

declare(strict_types=1);

namespace Tests\Unit\Services\System;

use App\Enums\ServiceStatus;
use App\Services\System\ServiceStatusService;
use Tests\TestCase;

class ServiceStatusServiceTest extends TestCase
{
    public function test_php_version_matches_the_running_interpreter(): void
    {
        $this->assertSame(PHP_VERSION, (new ServiceStatusService)->phpVersion());
    }

    public function test_check_all_returns_a_status_for_every_known_service(): void
    {
        $statuses = (new ServiceStatusService)->checkAll();

        $this->assertArrayHasKey('mariadb', $statuses);
        $this->assertArrayHasKey('redis', $statuses);
        $this->assertArrayHasKey('nginx', $statuses);
        $this->assertArrayHasKey('cloudflare_tunnel', $statuses);

        foreach ($statuses as $status) {
            $this->assertInstanceOf(ServiceStatus::class, $status);
        }
    }

    public function test_database_status_is_running_when_connection_works(): void
    {
        $statuses = (new ServiceStatusService)->checkAll();

        $this->assertSame(ServiceStatus::Running, $statuses['mariadb']);
    }

    public function test_nginx_and_cloudflare_tunnel_report_unavailable_off_linux(): void
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $this->markTestSkipped('Only applies off Linux.');
        }

        $statuses = (new ServiceStatusService)->checkAll();

        $this->assertSame(ServiceStatus::Unavailable, $statuses['nginx']);
        $this->assertSame(ServiceStatus::Unavailable, $statuses['cloudflare_tunnel']);
    }
}
