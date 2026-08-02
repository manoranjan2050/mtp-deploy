<?php

declare(strict_types=1);

namespace App\Services\Deployments;

use App\Enums\DeploymentStepStatus;
use App\Models\Deployment;
use App\Models\DeploymentStep;
use Symfony\Component\Process\Process;

/**
 * Runs the Laravel-specific half of a deployment - composer + artisan
 * commands - after GitDeploymentService has already gotten the right commit
 * checked out. Each step is its own DeploymentStep row (visible in the
 * panel), executed via real Symfony\Process against the website's actual
 * document_root; the first failing step halts the rest (Forge-style
 * abort-on-first-failure), and the whole pipeline's success/failure feeds
 * back into the parent Deployment's final status.
 *
 * Uses the currently-running PHP interpreter (`PHP_BINARY`) for `artisan`
 * calls rather than a per-website PHP version binary - a reasonable
 * simplification until Module 18 (Multi Server) needs to run this over SSH
 * against a server with multiple PHP-FPM versions installed side by side.
 */
class LaravelDeploymentPipelineService
{
    /**
     * @return list<array{name: string, command: list<string>}>
     */
    private function steps(): array
    {
        return [
            ['name' => 'composer install', 'command' => ['composer', 'install', '--no-interaction', '--prefer-dist', '--optimize-autoloader']],
            ['name' => 'artisan storage:link', 'command' => [PHP_BINARY, 'artisan', 'storage:link']],
            ['name' => 'artisan config:cache', 'command' => [PHP_BINARY, 'artisan', 'config:cache']],
            ['name' => 'artisan route:cache', 'command' => [PHP_BINARY, 'artisan', 'route:cache']],
            ['name' => 'artisan view:cache', 'command' => [PHP_BINARY, 'artisan', 'view:cache']],
            ['name' => 'artisan migrate', 'command' => [PHP_BINARY, 'artisan', 'migrate', '--force']],
            ['name' => 'artisan queue:restart', 'command' => [PHP_BINARY, 'artisan', 'queue:restart']],
        ];
    }

    public function run(Deployment $deployment): bool
    {
        $cwd = $deployment->website->document_root;

        foreach ($this->steps() as $index => $step) {
            $stepModel = DeploymentStep::query()->create([
                'deployment_id' => $deployment->id,
                'name' => $step['name'],
                'order' => $index,
                'status' => DeploymentStepStatus::Running,
                'started_at' => now(),
            ]);

            $process = new Process($step['command'], $cwd);
            $process->setTimeout(600);
            $process->run();

            $stepModel->update([
                'output' => trim($process->getOutput().$process->getErrorOutput()),
                'status' => $process->isSuccessful() ? DeploymentStepStatus::Success : DeploymentStepStatus::Failed,
                'finished_at' => now(),
            ]);

            $deployment->appendLog("\$ {$step['name']}\n".$stepModel->output);

            if (! $process->isSuccessful()) {
                return false;
            }
        }

        return true;
    }
}
