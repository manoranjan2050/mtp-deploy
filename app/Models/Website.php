<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SslStatus;
use App\Enums\WebsiteFramework;
use App\Enums\WebsiteStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
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
        'repository_url',
        'git_branch',
        'auto_deploy',
        'backups_enabled',
        'backup_retention_count',
        'created_by',
    ];

    protected $hidden = [
        'webhook_token',
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
        'git_branch' => 'main',
        'auto_deploy' => false,
        'backups_enabled' => false,
        'backup_retention_count' => 7,
    ];

    protected static function booted(): void
    {
        static::creating(function (Website $website): void {
            $website->webhook_token ??= Str::random(40);
        });
    }

    protected function casts(): array
    {
        return [
            'aliases' => 'array',
            'framework' => WebsiteFramework::class,
            'status' => WebsiteStatus::class,
            'ssl_status' => SslStatus::class,
            'auto_deploy' => 'boolean',
            'backups_enabled' => 'boolean',
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

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class)->latest('id');
    }

    public function cloudflareZone(): HasOne
    {
        return $this->hasOne(CloudflareZone::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(SslCertificate::class)->latest('id');
    }

    public function currentCertificate(): ?SslCertificate
    {
        return $this->certificates()
            ->whereIn('status', ['active', 'expiring'])
            ->first();
    }

    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class)->latest('id');
    }

    public function databases(): HasMany
    {
        return $this->hasMany(Database::class);
    }

    public function latestSuccessfulDeployment(): ?Deployment
    {
        return $this->deployments()->where('status', 'success')->first();
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
