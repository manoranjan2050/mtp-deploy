<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeploymentProvider;
use App\Enums\DeploymentStatus;
use App\Enums\DeploymentTrigger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deployment extends Model
{
    protected $fillable = [
        'website_id',
        'provider',
        'branch',
        'commit_sha',
        'status',
        'triggered_by',
        'triggered_by_user_id',
        'started_at',
        'finished_at',
        'log',
    ];

    protected $attributes = [
        'provider' => 'manual',
        'status' => 'pending',
        'triggered_by' => 'manual',
    ];

    protected function casts(): array
    {
        return [
            'provider' => DeploymentProvider::class,
            'status' => DeploymentStatus::class,
            'triggered_by' => DeploymentTrigger::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(DeploymentStep::class)->orderBy('order');
    }

    public function appendLog(string $line): void
    {
        $this->forceFill(['log' => rtrim((string) $this->log)."\n".$line])->save();
    }
}
