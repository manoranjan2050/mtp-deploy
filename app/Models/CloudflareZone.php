<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CloudflareSslMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CloudflareZone extends Model
{
    protected $fillable = [
        'website_id',
        'zone_id',
        'api_token',
        'ssl_mode',
        'last_synced_at',
    ];

    protected $hidden = [
        'api_token',
    ];

    /**
     * In-memory default matching the migration's DB default - see CLAUDE.md's
     * recurring-bug-class note.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'ssl_mode' => 'flexible',
    ];

    protected function casts(): array
    {
        return [
            'api_token' => 'encrypted',
            'ssl_mode' => CloudflareSslMode::class,
            'last_synced_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
