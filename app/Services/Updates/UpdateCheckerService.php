<?php

declare(strict_types=1);

namespace App\Services\Updates;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

/**
 * Compares this installation's checked-out git commit (this app is always
 * deployed via `git clone` per install.sh, so a real `.git` directory exists)
 * against the latest commit on the public repo's default branch, via
 * GitHub's public REST API. No auth token needed for a public repo; the
 * result is cached to stay well under GitHub's 60 req/hour unauthenticated
 * rate limit for a single server polling occasionally.
 */
class UpdateCheckerService
{
    public function currentCommit(): ?string
    {
        $process = new Process(['git', 'rev-parse', 'HEAD'], base_path());
        $process->run();

        return $process->isSuccessful() ? trim($process->getOutput()) : null;
    }

    public function latestRemoteCommit(): ?string
    {
        return Cache::remember('mtp:latest-remote-commit', now()->addHour(), function (): ?string {
            $repo = config('mtp.github_repo');
            $branch = config('mtp.github_branch');

            $response = Http::acceptJson()
                ->timeout(5)
                ->get("https://api.github.com/repos/{$repo}/commits/{$branch}");

            return $response->successful() ? $response->json('sha') : null;
        });
    }

    public function isUpdateAvailable(): bool
    {
        $current = $this->currentCommit();
        $latest = $this->latestRemoteCommit();

        return $current !== null && $latest !== null && $current !== $latest;
    }
}
