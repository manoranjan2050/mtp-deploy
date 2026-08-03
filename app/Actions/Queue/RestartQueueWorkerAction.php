<?php

declare(strict_types=1);

namespace App\Actions\Queue;

use App\DTOs\System\SystemCommandResult;
use App\Models\QueueWorker;
use App\Services\Queue\SupervisorProcessService;

class RestartQueueWorkerAction
{
    public function __construct(
        private readonly SupervisorProcessService $supervisor,
    ) {}

    public function handle(QueueWorker $worker): SystemCommandResult
    {
        $result = $this->supervisor->restart($worker);

        $worker->update(['status' => $result->successful ? 'running' : 'unknown']);

        activity('queue')
            ->causedBy(auth()->user())
            ->performedOn($worker->website)
            ->withProperties(['successful' => $result->successful])
            ->log('restarted queue worker');

        return $result;
    }
}
