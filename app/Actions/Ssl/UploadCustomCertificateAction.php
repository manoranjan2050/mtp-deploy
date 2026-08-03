<?php

declare(strict_types=1);

namespace App\Actions\Ssl;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Models\SslCertificate;
use App\Models\Website;
use App\Services\Ssl\CertificateParserException;
use App\Services\Ssl\CertificateParserService;
use App\Services\Ssl\CertificateStorageService;

class UploadCustomCertificateAction
{
    public function __construct(
        private readonly CertificateParserService $parser,
        private readonly CertificateStorageService $storage,
    ) {}

    public function handle(Website $website, string $certificatePem, string $privateKeyPem): SslCertificate
    {
        if (! $this->parser->certificateMatchesPrivateKey($certificatePem, $privateKeyPem)) {
            throw new CertificateParserException('The certificate does not match the provided private key.');
        }

        $details = $this->parser->parse($certificatePem);

        $certificate = SslCertificate::query()->create([
            'website_id' => $website->id,
            'type' => CertificateType::Custom,
            'domains' => $details->domains,
            'certificate' => $certificatePem,
            'private_key' => $privateKeyPem,
            'issued_at' => $details->issuedAt,
            'expires_at' => $details->expiresAt,
            'status' => CertificateStatus::Active,
            'auto_renew' => false,
        ]);

        $this->storage->write($website, $certificatePem, $privateKeyPem);
        $certificate->syncWebsiteSslStatus();

        activity('ssl')
            ->causedBy(auth()->user())
            ->performedOn($website)
            ->withProperties(['domains' => $details->domains, 'type' => 'custom'])
            ->log('uploaded custom certificate');

        return $certificate;
    }
}
