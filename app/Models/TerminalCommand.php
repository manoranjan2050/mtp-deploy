<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TerminalCommandStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerminalCommand extends Model
{
    protected $fillable = [
        'terminal_session_id',
        'user_id',
        'command',
        'output',
        'exit_code',
        'status',
        'executed_at',
    ];

    /**
     * In-memory default matching the migration's DB default - see CLAUDE.md's
     * recurring-bug-class note (Eloquent doesn't hydrate DB column defaults
     * onto a freshly-created, unrefreshed instance).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'executed',
    ];

    protected function casts(): array
    {
        return [
            'status' => TerminalCommandStatus::class,
            'executed_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TerminalSession::class, 'terminal_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
