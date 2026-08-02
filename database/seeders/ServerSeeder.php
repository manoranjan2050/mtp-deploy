<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ServerStatus;
use App\Models\Server;
use Illuminate\Database\Seeder;

/**
 * Every module through Module 17 operates against "this" server - Module 18
 * (Multi Server) is what makes the `servers` table meaningfully multi-row.
 * This seeds the one local, always-present row so Website Manager (Module 3)
 * has something to foreign-key against.
 */
class ServerSeeder extends Seeder
{
    public function run(): void
    {
        Server::query()->firstOrCreate(
            ['is_local' => true],
            [
                'name' => 'Local Server',
                'hostname' => gethostname() ?: 'localhost',
                'is_local' => true,
                'status' => ServerStatus::Connected,
                'os' => PHP_OS_FAMILY,
            ]
        );
    }
}
