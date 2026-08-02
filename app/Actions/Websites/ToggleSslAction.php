<?php

declare(strict_types=1);

namespace App\Actions\Websites;

use App\Enums\SslStatus;
use App\Models\Website;

/**
 * Module 3 only toggles *intent* - actual certificate issuance/renewal (Let's
 * Encrypt, custom certs, wildcard) is Module 10's job, which depends on this
 * module. Enabling here marks the site `Pending` so Module 10 knows to pick
 * it up; it does not fabricate an `Active` SSL status without a real
 * certificate ever having been issued (see docs/Vision.md).
 */
class ToggleSslAction
{
    public function enable(Website $website): Website
    {
        $website->update(['ssl_status' => SslStatus::Pending]);

        return $website->fresh();
    }

    public function disable(Website $website): Website
    {
        $website->update(['ssl_status' => SslStatus::None]);

        return $website->fresh();
    }
}
