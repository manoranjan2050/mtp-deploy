<?php

declare(strict_types=1);

namespace App\Actions\Cron;

use App\Models\CronJob;
use App\Services\Cron\CronJobRunnerService;

class RunCronJobNowAction
{
    public function __construct(
        private readonly CronJobRunnerService $runner,
    ) {}

    public function handle(CronJob $job): CronJob
    {
        $this->runner->run($job);

        activity('cron')
            ->causedBy(auth()->user())
            ->performedOn($job->server)
            ->withProperties(['cron_job_id' => $job->id, 'exit_code' => $job->fresh()->last_exit_code])
            ->log('ran cron job manually');

        return $job->fresh();
    }
}
