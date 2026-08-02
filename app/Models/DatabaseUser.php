<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DatabaseUser extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'server_id',
        'username',
        'password',
        'host',
        'created_by',
    ];

    protected $attributes = [
        'host' => '%',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function databases(): BelongsToMany
    {
        return $this->belongsToMany(Database::class, 'database_user_database')
            ->using(DatabaseUserDatabase::class)
            ->withPivot('privileges')
            ->withTimestamps();
    }
}
