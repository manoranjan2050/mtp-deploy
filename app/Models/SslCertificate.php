<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Enums\SslStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SslCertificate extends Model
{
    protected $fillable = [
        'website_id',
        'type',
        'domains',
        'certificate',
        'private_key',
        'issued_at',
        'expires_at',
        'status',
        'auto_renew',
        'last_renewal_attempt_at',
        'last_error',
    ];

    protected $hidden = [
        'private_key',
    ];

    /**
     * In-memory defaults matching the migration's DB defaults - see
     * CLAUDE.md's recurring-bug-class note.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'lets_encrypt',
        'status' => 'pending',
        'auto_renew' => true,
    ];

    protected function casts(): array
    {
        return [
            'domains' => 'array',
            'type' => CertificateType::class,
            'status' => CertificateStatus::class,
            'auto_renew' => 'boolean',
            'private_key' => 'encrypted',
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_renewal_attempt_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function isDueForRenewal(int $thresholdDays): bool
    {
        return $this->auto_renew
            && $this->type === CertificateType::LetsEncrypt
            && $this->status === CertificateStatus::Active
            && $this->expires_at !== null
            && $this->expires_at->lessThanOrEqualTo(now()->addDays($thresholdDays));
    }

    /**
     * Keeps `Website::ssl_status` (Module 3) truthful about whatever this
     * certificate's real status is - never left saying "Active" once the
     * underlying certificate is revoked/expired/failed.
     */
    public function syncWebsiteSslStatus(): void
    {
        $status = match ($this->status) {
            CertificateStatus::Active, CertificateStatus::Expiring => SslStatus::Active,
            CertificateStatus::Expired => SslStatus::Expired,
            CertificateStatus::Pending, CertificateStatus::Failed => SslStatus::Pending,
            CertificateStatus::Revoked => SslStatus::None,
        };

        $this->website()->update(['ssl_status' => $status]);
    }
}
