<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Ssl\RenewCertificateAction;
use App\Models\SslCertificate;
use App\Services\Ssl\AcmeException;
use Illuminate\Console\Command;

class RenewSslCertificates extends Command
{
    protected $signature = 'app:renew-ssl-certificates';

    protected $description = "Renew any Let's Encrypt certificate nearing expiry (see mtp.ssl_renewal_threshold_days)";

    public function handle(RenewCertificateAction $renew): int
    {
        $threshold = (int) config('mtp.ssl_renewal_threshold_days', 30);

        $due = SslCertificate::query()->get()->filter(fn (SslCertificate $certificate): bool => $certificate->isDueForRenewal($threshold));

        foreach ($due as $certificate) {
            $this->info("Renewing certificate for {$certificate->website->domain}...");

            try {
                $renew->handle($certificate);
                $this->info('  succeeded.');
            } catch (AcmeException $exception) {
                $this->error("  failed: {$exception->getMessage()}");
            }
        }

        if ($due->isEmpty()) {
            $this->info('No certificates are due for renewal.');
        }

        return self::SUCCESS;
    }
}
