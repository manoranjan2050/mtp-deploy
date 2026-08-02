<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only Eloquent wrapper around Laravel's own database session driver
 * table, so Filament's table component (which requires an Eloquent query,
 * not a raw query builder) can list a user's active sessions.
 */
class Session extends Model
{
    protected $table = 'sessions';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
