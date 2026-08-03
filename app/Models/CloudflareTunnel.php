<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CloudflareTunnelStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CloudflareTunnel extends Model
{
    protected $fillable = [
        'server_id',
        'cloudflare_tunnel_id',
        'name',
        'status',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'inactive',
    ];

    protected function casts(): array
    {
        return [
            'status' => CloudflareTunnelStatus::class,
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
