<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Dedicated pivot model for the database_user_database table - needed
 * because Eloquent does not auto-cast pivot attributes (the `privileges`
 * JSON column) without one; a plain array passed to sync()/attach() on the
 * default anonymous pivot fails with "Array to string conversion".
 */
class DatabaseUserDatabase extends Pivot
{
    protected $table = 'database_user_database';

    protected function casts(): array
    {
        return [
            'privileges' => 'array',
        ];
    }
}
