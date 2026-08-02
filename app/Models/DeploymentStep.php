<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeploymentStepStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeploymentStep extends Model
{
    protected $fillable = [
        'deployment_id',
        'name',
        'status',
        'output',
        'order',
        'started_at',
        'finished_at',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeploymentStepStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function deployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class);
    }
}
