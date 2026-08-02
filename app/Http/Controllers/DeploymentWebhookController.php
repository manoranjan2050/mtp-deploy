<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Deployments\TriggerDeploymentAction;
use App\Enums\DeploymentTrigger;
use App\Models\Website;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Inbound Git-provider webhook receiver - authenticated by a per-website
 * random token in the URL (not Sanctum, since GitHub/GitLab/Bitbucket can't
 * send our bearer tokens) plus, when the provider sends one, an HMAC
 * signature verified against that same per-website token used as the shared
 * webhook secret. See docs/API.md and docs/Security.md.
 */
class DeploymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $webhookToken, TriggerDeploymentAction $trigger): SymfonyResponse
    {
        $website = Website::query()->where('webhook_token', $webhookToken)->first();

        if (! $website) {
            return response('Not found', SymfonyResponse::HTTP_NOT_FOUND);
        }

        if (! $website->auto_deploy) {
            return response('Auto-deploy is disabled for this website', SymfonyResponse::HTTP_FORBIDDEN);
        }

        if (! $this->hasValidSignature($request, $website->webhook_token)) {
            return response('Invalid signature', SymfonyResponse::HTTP_FORBIDDEN);
        }

        $deployment = $trigger->handle($website, DeploymentTrigger::Webhook);

        return response()->json([
            'deployment_id' => $deployment->id,
            'status' => $deployment->status->value,
        ]);
    }

    /**
     * GitHub sends `X-Hub-Signature-256: sha256=<hmac>`. If the request
     * doesn't carry that header at all (GitLab uses a plain shared-secret
     * header instead, Bitbucket sends neither), the URL token itself is the
     * auth boundary - it's a random 40-character value, not guessable. If the
     * header IS present, it must be correct; a present-but-wrong signature
     * always fails, it never silently falls back to "token was enough."
     */
    private function hasValidSignature(Request $request, string $secret): bool
    {
        $signatureHeader = $request->header('X-Hub-Signature-256');

        if ($signatureHeader === null) {
            return true;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signatureHeader);
    }
}
