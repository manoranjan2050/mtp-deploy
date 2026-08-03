<?php

declare(strict_types=1);

namespace App\Models;

use Cron\CronExpression;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CronJob extends Model
{
    protected $fillable = [
        'server_id',
        'website_id',
        'created_by',
        'label',
        'command',
        'schedule',
        'is_enabled',
        'last_run_at',
        'last_exit_code',
        'last_output',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'last_run_at' => 'datetime',
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

    /**
     * The crontab line this job renders as, e.g. `* * * * * php artisan foo`.
     * Wrapping every command in `sh -c '...'` would be more forgiving of
     * shell operators (&&, |, >), but real crontab already invokes each
     * command via `/bin/sh -c`, so no extra wrapping is needed here.
     */
    public function toCrontabLine(): string
    {
        return "{$this->schedule} {$this->command}";
    }

    public function nextRunAt(): ?\DateTimeInterface
    {
        try {
            return (new CronExpression($this->schedule))->getNextRunDate();
        } catch (\Exception) {
            return null;
        }
    }
}
