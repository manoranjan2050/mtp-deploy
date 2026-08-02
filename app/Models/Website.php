<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SslStatus;
use App\Enums\WebsiteFramework;
use App\Enums\WebsiteStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Website extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'server_id',
        'name',
        'domain',
        'aliases',
        'document_root',
        'php_version',
        'framework',
        'status',
        'ssl_status',
        'created_by',
    ];

    /**
     * In-memory defaults matching this table's DB column defaults. Eloquent
     * does not hydrate DB-applied defaults onto a freshly-created instance
     * (only a re-fetch does) - without this, `$website->status` and
     * `$website->framework` read as null (not their enum default) on a
     * brand new, unrefreshed model, and any enum `match()` against them
     * throws `UnhandledMatchError`. Same class of bug fixed on `User::
     * is_active` in Module 2 - see TODO.md.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'framework' => 'laravel',
        'status' => 'active',
        'ssl_status' => 'none',
    ];

    protected function casts(): array
    {
        return [
            'aliases' => 'array',
            'framework' => WebsiteFramework::class,
            'status' => WebsiteStatus::class,
            'ssl_status' => SslStatus::class,
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The directory nginx actually serves from - `document_root` plus the
     * framework's public-folder suffix (Laravel serves from `/public`).
     */
    public function publicPath(): string
    {
        return rtrim($this->document_root, '/').$this->framework->documentRootSuffix();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'domain', 'php_version', 'status', 'ssl_status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
