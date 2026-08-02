<?php

declare(strict_types=1);

namespace App\Services\Deployments;

use App\Enums\DeploymentStatus;
use App\Enums\DeploymentTrigger;
use App\Models\Deployment;
use App\Models\User;
use App\Models\Website;
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
 * This only covers the "get the right commit checked out" half of deployment
 * (Module 5). The Laravel-specific pipeline (composer install, artisan
 * migrate, etc.) is Module 6's job and layers on top of a successful deploy
 * here.
 */
class GitDeploymentService
{
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

            $deployment->update([
                'commit_sha' => $commitSha,
                'status' => DeploymentStatus::Success,
                'finished_at' => now(),
            ]);
        } catch (ProcessFailedException $exception) {
            $deployment->appendLog($exception->getMessage());
            $deployment->update([
                'status' => DeploymentStatus::Failed,
                'finished_at' => now(),
            ]);
        }

        return $deployment->fresh();
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
