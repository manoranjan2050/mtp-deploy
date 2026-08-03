<?php

declare(strict_types=1);

namespace App\Services\Backups;

use App\Models\Website;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Real `git` commands against a bare "shadow" repository per website - not a
 * placeholder, same "real infrastructure" precedent as
 * `App\Services\Deployments\GitDeploymentService` (Module 5). The shadow
 * repo lives under storage/, entirely separate from any repository the
 * website itself might be deployed from (Module 5) - this is a backup
 * mechanism, not a deployment one, and must survive even if the website's
 * own `.git` directory (if any) is lost.
 *
 * `--git-dir`/`--work-tree` let every command run without `cd`-ing into the
 * website's document root; `-c user.name=/-c user.email=` are passed
 * per-invocation so this doesn't depend on system-wide git config existing
 * on a fresh server.
 */
class GitBackupService
{
    public function snapshot(Website $website): string
    {
        $gitDir = $this->gitDirFor($website);
        $workTree = rtrim($website->document_root, '/\\');

        if (! File::isDirectory($gitDir)) {
            File::ensureDirectoryExists(dirname($gitDir));
            $this->run(['git', 'init', '--bare', $gitDir]);
        }

        $this->run([...$this->baseArgs($gitDir, $workTree), 'add', '-A']);
        $this->run([
            ...$this->baseArgs($gitDir, $workTree),
            'commit',
            '--allow-empty',
            '-m',
            'Backup snapshot '.now()->toIso8601String(),
        ]);

        $process = new Process([...$this->baseArgs($gitDir, $workTree), 'rev-parse', 'HEAD']);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Could not read the snapshot commit SHA: '.$process->getErrorOutput());
        }

        return trim($process->getOutput());
    }

    public function restore(Website $website, string $commitSha): void
    {
        $gitDir = $this->gitDirFor($website);
        $workTree = rtrim($website->document_root, '/\\');

        if (! File::isDirectory($gitDir)) {
            throw new RuntimeException("No git backup history exists for {$website->domain}.");
        }

        $this->run([...$this->baseArgs($gitDir, $workTree), 'checkout', $commitSha, '--', '.']);
    }

    /**
     * @return list<array{sha: string, date: string, message: string}>
     */
    public function history(Website $website, int $limit = 20): array
    {
        $gitDir = $this->gitDirFor($website);

        if (! File::isDirectory($gitDir)) {
            return [];
        }

        $process = new Process([
            'git', '--git-dir='.$gitDir,
            'log', '-'.$limit, '--pretty=format:%H|%ci|%s',
        ]);
        $process->run();

        if (! $process->isSuccessful() || trim($process->getOutput()) === '') {
            return [];
        }

        return collect(explode("\n", trim($process->getOutput())))
            ->map(function (string $line): array {
                [$sha, $date, $message] = array_pad(explode('|', $line, 3), 3, '');

                return ['sha' => $sha, 'date' => $date, 'message' => $message];
            })
            ->all();
    }

    private function gitDirFor(Website $website): string
    {
        return rtrim((string) config('mtp.git_backups_path'), '/').'/'.$website->domain.'.git';
    }

    /**
     * @return list<string>
     */
    private function baseArgs(string $gitDir, string $workTree): array
    {
        return [
            'git',
            '-c', 'user.name=MTP Deploy',
            '-c', 'user.email=backups@mtp-deploy.local',
            '--git-dir='.$gitDir,
            '--work-tree='.$workTree,
        ];
    }

    /**
     * @param  list<string>  $command
     */
    private function run(array $command): void
    {
        $process = new Process($command);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('git command failed: '.implode(' ', $command)."\n".$process->getErrorOutput());
        }
    }
}
