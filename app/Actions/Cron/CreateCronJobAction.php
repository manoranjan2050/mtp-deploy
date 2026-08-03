<?php

declare(strict_types=1);

namespace App\Actions\Cron;

use App\DTOs\Cron\CronJobData;
use App\Models\CronJob;
use App\Models\Server;
use App\Services\Cron\SystemCrontabService;

class CreateCronJobAction
{
    public function __construct(
        private readonly SystemCrontabService $crontab,
    ) {}

    public function handle(CronJobData $data): CronJob
    {
        $job = CronJob::query()->create([
            'server_id' => $data->serverId,
            'website_id' => $data->websiteId,
            'created_by' => $data->createdBy,
            'label' => $data->label,
            'command' => $data->command,
            'schedule' => $data->schedule,
            'is_enabled' => $data->isEnabled,
        ]);

        $this->crontab->sync(Server::query()->findOrFail($data->serverId));

        activity('cron')
            ->causedBy(auth()->user())
            ->performedOn($job->server)
            ->withProperties(['label' => $data->label, 'schedule' => $data->schedule])
            ->log('created cron job');

        return $job;
    }
}
