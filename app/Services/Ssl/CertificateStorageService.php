<?php

declare(strict_types=1);

namespace App\Services\Ssl;

use App\Models\Website;
use Illuminate\Support\Facades\File;

/**
 * Writes the currently-active certificate/private key to disk as plain PEM
 * files so nginx can reference them by path - a separate concern from
 * persisting them (encrypted) in the database, the same split as
 * WebsiteProvisioningService (DB record vs. on-disk vhost file) in Module 3.
 */
class CertificateStorageService
{
    /**
     * @return array{certificate: string, privateKey: string} absolute file paths
     */
    public function paths(Website $website): array
    {
        $root = rtrim((string) config('mtp.ssl_certificates_path'), '/');

        return [
            'certificate' => "{$root}/{$website->domain}.crt",
            'privateKey' => "{$root}/{$website->domain}.key",
        ];
    }

    public function write(Website $website, string $certificatePem, string $privateKeyPem): void
    {
        $paths = $this->paths($website);

        File::ensureDirectoryExists(dirname($paths['certificate']));
        File::put($paths['certificate'], $certificatePem);
        File::put($paths['privateKey'], $privateKeyPem);
    }

    public function remove(Website $website): void
    {
        $paths = $this->paths($website);

        File::delete($paths['certificate']);
        File::delete($paths['privateKey']);
    }
}
