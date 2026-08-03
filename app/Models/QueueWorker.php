<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QueueWorkerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QueueWorker extends Model
{
    protected $fillable = [
        'website_id',
        'created_by',
        'connection',
        'queue',
        'processes',
        'status',
        'supervisor_program_name',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'connection' => 'database',
        'queue' => 'default',
        'processes' => 1,
        'status' => 'stopped',
    ];

    protected static function booted(): void
    {
        static::creating(function (QueueWorker $worker): void {
            $worker->supervisor_program_name ??= 'mtp-website-'.$worker->website_id.'-'.Str::random(8);
        });
    }

    protected function casts(): array
    {
        return [
            'processes' => 'integer',
            'status' => QueueWorkerStatus::class,
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
