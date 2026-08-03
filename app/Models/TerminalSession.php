<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TerminalSession extends Model
{
    protected $fillable = [
        'server_id',
        'user_id',
        'label',
        'current_directory',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function commands(): HasMany
    {
        return $this->hasMany(TerminalCommand::class)->latest('id');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }
}
