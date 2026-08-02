<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemMetricSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'is_supported',
        'cpu_usage_percent',
        'memory_used_bytes',
        'memory_total_bytes',
        'disk_used_bytes',
        'disk_total_bytes',
        'load_1min',
        'load_5min',
        'load_15min',
        'network_rx_bytes',
        'network_tx_bytes',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'is_supported' => 'boolean',
            'recorded_at' => 'datetime',
        ];
    }
}
