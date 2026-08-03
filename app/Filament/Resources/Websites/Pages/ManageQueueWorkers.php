<?php

declare(strict_types=1);

namespace App\Filament\Resources\Websites\Pages;

use App\Actions\Queue\CreateQueueWorkerAction;
use App\Actions\Queue\DeleteQueueWorkerAction;
use App\Actions\Queue\RestartQueueWorkerAction;
use App\Actions\Queue\StartQueueWorkerAction;
use App\Actions\Queue\StopQueueWorkerAction;
use App\DTOs\Queue\QueueWorkerData;
use App\Filament\Resources\Websites\WebsiteResource;
use App\Models\QueueWorker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;

class ManageQueueWorkers extends Page
{
    use InteractsWithRecord;

    protected static string $resource = WebsiteResource::class;

    protected string $view = 'filament.resources.websites.pages.manage-queue-workers';

    #[Validate('required|string|max:255')]
    public string $connection = 'database';

    #[Validate('required|string|max:255')]
    public string $queue = 'default';

    #[Validate('required|integer|min:1|max:20')]
    public int $processes = 1;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(auth()->user()->can('update', $this->getRecord()), 403);
    }

    /**
     * @return Collection<int, QueueWorker>
     */
    #[Computed]
    public function workers(): Collection
    {
        return $this->getRecord()->queueWorkers()->latest('id')->get();
    }

    public function createWorker(): void
    {
        $this->validate();

        $result = app(CreateQueueWorkerAction::class)->handle(new QueueWorkerData(
            websiteId: $this->getRecord()->id,
            connection: $this->connection,
            queue: $this->queue,
            processes: $this->processes,
            createdBy: auth()->id(),
        ));

        $this->connection = 'database';
        $this->queue = 'default';
        $this->processes = 1;

        unset($this->workers);

        Notification::make()
            ->title($result['result']->successful ? 'Queue worker created and started' : 'Queue worker created, but Supervisor could not be reloaded')
            ->body($result['result']->successful ? null : $result['result']->errorOutput)
            ->success($result['result']->successful)
            ->warning(! $result['result']->successful)
            ->send();
    }

    public function startWorker(int $workerId): void
    {
        $result = app(StartQueueWorkerAction::class)->handle($this->findWorker($workerId));

        unset($this->workers);

        $this->notifyResult($result->successful, 'Worker started', 'Could not start worker', $result->errorOutput);
    }

    public function stopWorker(int $workerId): void
    {
        $result = app(StopQueueWorkerAction::class)->handle($this->findWorker($workerId));

        unset($this->workers);

        $this->notifyResult($result->successful, 'Worker stopped', 'Could not stop worker', $result->errorOutput);
    }

    public function restartWorker(int $workerId): void
    {
        $result = app(RestartQueueWorkerAction::class)->handle($this->findWorker($workerId));

        unset($this->workers);

        $this->notifyResult($result->successful, 'Worker restarted', 'Could not restart worker', $result->errorOutput);
    }

    public function deleteWorker(int $workerId): void
    {
        app(DeleteQueueWorkerAction::class)->handle($this->findWorker($workerId));

        unset($this->workers);

        Notification::make()->title('Queue worker deleted')->success()->send();
    }

    private function findWorker(int $workerId): QueueWorker
    {
        return QueueWorker::query()->where('website_id', $this->getRecord()->id)->findOrFail($workerId);
    }

    private function notifyResult(bool $successful, string $successTitle, string $failureTitle, string $errorOutput): void
    {
        Notification::make()
            ->title($successful ? $successTitle : $failureTitle)
            ->body($successful ? null : $errorOutput)
            ->success($successful)
            ->warning(! $successful)
            ->send();
    }
}
