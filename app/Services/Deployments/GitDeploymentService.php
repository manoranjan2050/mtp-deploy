<?php

declare(strict_types=1);

namespace App\Services\Deployments;

use App\Enums\DeploymentStatus;
use App\Enums\DeploymentTrigger;
use App\Enums\WebhookEvent;
use App\Enums\WebsiteFramework;
use App\Models\Deployment;
use App\Models\User;
use App\Models\Website;
use App\Services\Notifications\NotificationDispatchService;
use App\Services\Webhooks\WebhookDispatchService;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Runs real `git` commands (clone/fetch/checkout/reset/rev-parse) against a
 * website's `document_root` - not a placeholder. In tests, `repository_url`
 * points at a real local bare git repository fixture (see
 * tests/Feature/Deployments/), so the whole clone -> deploy -> rollback cycle
 * is exercised for real without needing network access to GitHub/GitLab.
 *
 * Once the right commit is checked out, Laravel websites also run the
 * composer/artisan pipeline (Module 6's `LaravelDeploymentPipelineService`) -
 * a rollback is "deploy this other commit," so it gets the same pipeline
 * treatment (that old commit's dependencies need reinstalling, migrations
 * re-running, etc.), not just a bare git reset.
 */
class GitDeploymentService
{
    public function __construct(
        private readonly LaravelDeploymentPipelineService $pipeline,
        private readonly NotificationDispatchService $notifications,
        private readonly WebhookDispatchService $webhooks,
    ) {}

    public function deploy(
        Website $website,
        DeploymentTrigger $trigger,
        ?User $triggeredBy = null,
        ?string $commitish = null,
    ): Deployment {
        $deployment = Deployment::query()->create([
            'website_id' => $website->id,
            'branch' => $website->git_branch,
            'status' => DeploymentStatus::Running,
            'triggered_by' => $trigger,
            'triggered_by_user_id' => $triggeredBy?->id,
            'started_at' => now(),
        ]);

        // A plain deploy (no explicit commit) must land on the branch's
        // latest *remote* commit, not the local branch ref of the same name -
        // fetch only updates remote-tracking refs (origin/<branch>), it never
        // moves the local branch pointer. Resetting to the bare branch name
        // here would silently redeploy whatever was checked out at clone
        // time, forever. Rollback passes an explicit commit SHA instead,
        // which is used as-is.
        $target = $commitish ?? "origin/{$website->git_branch}";

        try {
            $this->ensureRepositoryCloned($website, $deployment);
            $this->fetch($website, $deployment);
            $this->checkout($website, $deployment, $target);

            $commitSha = $this->currentCommitSha($website);
            $deployment->update(['commit_sha' => $commitSha]);

            $pipelineSucceeded = $website->framework !== WebsiteFramework::Laravel
                || $this->pipeline->run($deployment);

            $deployment->update([
                'status' => $pipelineSucceeded ? DeploymentStatus::Success : DeploymentStatus::Failed,
                'finished_at' => now(),
            ]);
        } catch (ProcessFailedException $exception) {
            $deployment->appendLog($exception->getMessage());
            $deployment->update([
                'status' => DeploymentStatus::Failed,
                'finished_at' => now(),
            ]);
        }

        $deployment = $deployment->fresh();

        if ($triggeredBy !== null) {
            $this->notifications->notifyUser(
                $triggeredBy,
                "Deployment {$deployment->status->getLabel()}: {$website->domain}",
                "Branch {$deployment->branch}, commit {$deployment->commit_sha}.",
            );

            $this->webhooks->dispatchForUser(
                $triggeredBy,
                $deployment->status === DeploymentStatus::Failed ? WebhookEvent::DeploymentFailed : WebhookEvent::DeploymentSucceeded,
                [
                    'deployment_id' => $deployment->id,
                    'website_id' => $website->id,
                    'domain' => $website->domain,
                    'status' => $deployment->status->value,
                    'commit_sha' => $deployment->commit_sha,
                ],
            );
        }

        return $deployment;
    }

    private function ensureRepositoryCloned(Website $website, Deployment $deployment): void
    {
        if (File::isDirectory($website->document_root.'/.git')) {
            return;
        }

        File::ensureDirectoryExists(dirname($website->document_root));

        $this->run($deployment, [
            'git', 'clone', '--branch', $website->git_branch, $website->repository_url, $website->document_root,
        ], cwd: null);
    }

    private function fetch(Website $website, Deployment $deployment): void
    {
        $this->run($deployment, ['git', 'fetch', 'origin'], cwd: $website->document_root);
    }

    private function checkout(Website $website, Deployment $deployment, string $target): void
    {
        $this->run($deployment, ['git', 'reset', '--hard', $target], cwd: $website->document_root);
    }

    private function currentCommitSha(Website $website): string
    {
        $process = new Process(['git', 'rev-parse', 'HEAD'], $website->document_root);
        $process->run();

        return trim($process->getOutput());
    }

    /**
     * @param  list<string>  $command
     */
    private function run(Deployment $deployment, array $command, ?string $cwd): void
    {
        $process = new Process($command, $cwd);
        $process->setTimeout(300);
        $process->run();

        $deployment->appendLog('$ '.implode(' ', $command));
        $deployment->appendLog(trim($process->getOutput().$process->getErrorOutput()));

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }
}
