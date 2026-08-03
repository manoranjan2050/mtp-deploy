<?php

declare(strict_types=1);

namespace App\Actions\Cron;

use App\Models\CronJob;
use App\Services\Cron\SystemCrontabService;

class DeleteCronJobAction
{
    public function __construct(
        private readonly SystemCrontabService $crontab,
    ) {}

    public function handle(CronJob $job): void
    {
        $server = $job->server;
        $label = $job->label;

        $job->delete();

        $this->crontab->sync($server);

        activity('cron')
            ->causedBy(auth()->user())
            ->performedOn($server)
            ->withProperties(['label' => $label])
            ->log('deleted cron job');
    }
}
