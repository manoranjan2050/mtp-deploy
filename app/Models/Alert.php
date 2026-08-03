<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AlertMetric;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $fillable = [
        'server_id',
        'metric',
        'threshold_percent',
        'triggered_value_percent',
        'triggered_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'metric' => AlertMetric::class,
            'triggered_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
}
