<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DatabaseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Database extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'databases';

    protected $fillable = [
        'server_id',
        'website_id',
        'name',
        'charset',
        'collation',
        'status',
        'created_by',
    ];

    protected $attributes = [
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'status' => DatabaseStatus::class,
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(DatabaseUser::class, 'database_user_database')
            ->using(DatabaseUserDatabase::class)
            ->withPivot('privileges')
            ->withTimestamps();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
