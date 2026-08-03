<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    protected $fillable = [
        'name',
        'hostname',
        'ssh_host',
        'ssh_port',
        'ssh_user',
        'ssh_private_key',
        'is_local',
        'status',
        'os',
        'php_versions',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_local' => 'boolean',
            'status' => ServerStatus::class,
            'php_versions' => 'array',
            'ssh_private_key' => 'encrypted',
        ];
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }

    public function terminalSessions(): HasMany
    {
        return $this->hasMany(TerminalSession::class);
    }

    public function cloudflareTunnels(): HasMany
    {
        return $this->hasMany(CloudflareTunnel::class);
    }

    public function cronJobs(): HasMany
    {
        return $this->hasMany(CronJob::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * PHP versions selectable when creating/editing a website on this server.
     * Falls back to a curated common list when the server hasn't reported its
     * installed versions yet (e.g. the local server before Module 18 wires up
     * real detection).
     *
     * @return array<int, string>
     */
    public function availablePhpVersions(): array
    {
        return $this->php_versions ?: ['8.2', '8.3', '8.4'];
    }
}
