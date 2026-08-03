<?php

declare(strict_types=1);

namespace App\Actions\Ssl;

use App\Enums\CertificateStatus;
use App\Models\SslCertificate;
use App\Services\Ssl\CertificateStorageService;

class RevokeCertificateAction
{
    public function __construct(
        private readonly CertificateStorageService $storage,
    ) {}

    public function handle(SslCertificate $certificate): void
    {
        $website = $certificate->website;

        $certificate->update(['status' => CertificateStatus::Revoked]);

        if ($website->currentCertificate() === null) {
            $this->storage->remove($website);
        }

        $certificate->syncWebsiteSslStatus();

        activity('ssl')
            ->causedBy(auth()->user())
            ->performedOn($website)
            ->log('revoked certificate');
    }
}
