<?php

declare(strict_types=1);

namespace App\Actions\Queue;

use App\DTOs\Queue\QueueWorkerData;
use App\DTOs\System\SystemCommandResult;
use App\Models\QueueWorker;
use App\Services\Queue\SupervisorProcessService;

class CreateQueueWorkerAction
{
    public function __construct(
        private readonly SupervisorProcessService $supervisor,
    ) {}

    /**
     * @return array{worker: QueueWorker, result: SystemCommandResult}
     */
    public function handle(QueueWorkerData $data): array
    {
        $worker = QueueWorker::query()->create([
            'website_id' => $data->websiteId,
            'created_by' => $data->createdBy,
            'connection' => $data->connection,
            'queue' => $data->queue,
            'processes' => $data->processes,
        ]);

        $this->supervisor->writeConfig($worker);
        $result = $this->supervisor->reloadSupervisor();

        $worker->update(['status' => $result->successful ? 'running' : 'unknown']);

        activity('queue')
            ->causedBy(auth()->user())
            ->performedOn($worker->website)
            ->withProperties(['connection' => $data->connection, 'queue' => $data->queue])
            ->log('created queue worker');

        return ['worker' => $worker->fresh(), 'result' => $result];
    }
}
