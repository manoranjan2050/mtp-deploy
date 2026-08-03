<?php

declare(strict_types=1);

namespace App\Actions\Queue;

use App\Models\QueueWorker;
use App\Services\Queue\SupervisorProcessService;

class DeleteQueueWorkerAction
{
    public function __construct(
        private readonly SupervisorProcessService $supervisor,
    ) {}

    public function handle(QueueWorker $worker): void
    {
        $website = $worker->website;

        $this->supervisor->stop($worker);
        $this->supervisor->removeConfig($worker);
        $this->supervisor->reloadSupervisor();

        $worker->delete();

        activity('queue')
            ->causedBy(auth()->user())
            ->performedOn($website)
            ->log('deleted queue worker');
    }
}
