<?php

declare(strict_types=1);

namespace App\Actions\Cron;

use App\Models\CronJob;
use App\Services\Cron\SystemCrontabService;

class UpdateCronJobAction
{
    public function __construct(
        private readonly SystemCrontabService $crontab,
    ) {}

    /**
     * @param  array{label?: string, command?: string, schedule?: string, is_enabled?: bool}  $attributes
     */
    public function handle(CronJob $job, array $attributes): CronJob
    {
        $job->update($attributes);

        $this->crontab->sync($job->server);

        activity('cron')
            ->causedBy(auth()->user())
            ->performedOn($job->server)
            ->withProperties(['cron_job_id' => $job->id])
            ->log('updated cron job');

        return $job->fresh();
    }
}
