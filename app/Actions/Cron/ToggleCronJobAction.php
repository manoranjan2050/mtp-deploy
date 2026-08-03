<?php

declare(strict_types=1);

namespace App\Actions\Cron;

use App\Models\CronJob;
use App\Services\Cron\SystemCrontabService;

class ToggleCronJobAction
{
    public function __construct(
        private readonly SystemCrontabService $crontab,
    ) {}

    public function handle(CronJob $job): CronJob
    {
        $job->update(['is_enabled' => ! $job->is_enabled]);

        $this->crontab->sync($job->server);

        activity('cron')
            ->causedBy(auth()->user())
            ->performedOn($job->server)
            ->withProperties(['cron_job_id' => $job->id, 'is_enabled' => $job->is_enabled])
            ->log($job->is_enabled ? 'enabled cron job' : 'disabled cron job');

        return $job->fresh();
    }
}
