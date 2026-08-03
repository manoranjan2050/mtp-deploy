<?php

declare(strict_types=1);

namespace App\Actions\Ssl;

use App\Models\SslCertificate;
use App\Models\Website;
use App\Services\Ssl\AcmeException;
use App\Services\Ssl\LetsEncryptIssuanceService;

class IssueLetsEncryptCertificateAction
{
    public function __construct(
        private readonly LetsEncryptIssuanceService $issuance,
    ) {}

    /**
     * @param  list<string>  $domains
     */
    public function handle(Website $website, array $domains, bool $useDnsChallenge = false): SslCertificate
    {
        try {
            $certificate = $this->issuance->issue($website, $domains, $useDnsChallenge);

            activity('ssl')
                ->causedBy(auth()->user())
                ->performedOn($website)
                ->withProperties(['domains' => $domains, 'successful' => true])
                ->log("issued let's encrypt certificate");

            return $certificate;
        } catch (AcmeException $exception) {
            activity('ssl')
                ->causedBy(auth()->user())
                ->performedOn($website)
                ->withProperties(['domains' => $domains, 'successful' => false, 'error' => $exception->getMessage()])
                ->log("let's encrypt issuance failed");

            throw $exception;
        } finally {
            // The most recent certificate row is the one this issuance
            // attempt just created/updated, regardless of whether it ended
            // up Active or Failed - currentCertificate() would miss it in
            // the failure case, since that helper only ever returns an
            // active/expiring certificate.
            $website->fresh()->certificates()->first()?->syncWebsiteSslStatus();
        }
    }
}
