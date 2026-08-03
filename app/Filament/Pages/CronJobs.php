<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Cron\CreateCronJobAction;
use App\Actions\Cron\DeleteCronJobAction;
use App\Actions\Cron\RunCronJobNowAction;
use App\Actions\Cron\ToggleCronJobAction;
use App\DTOs\Cron\CronJobData;
use App\Models\CronJob;
use App\Models\Server;
use App\Rules\ValidCronExpression;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;

class CronJobs extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort = 17;

    protected string $view = 'filament.pages.cron-jobs';

    #[Validate('required|string|max:255')]
    public string $label = '';

    #[Validate('required|string|max:2000')]
    public string $command = '';

    public string $schedule = '* * * * *';

    public static function canAccess(): bool
    {
        $server = Server::query()->where('is_local', true)->first();

        return $server !== null && auth()->user()?->can('manageCronJobs', $server) === true;
    }

    public function mount(): void
    {
        $server = $this->server();

        abort_unless($server !== null && auth()->user()->can('manageCronJobs', $server), 403);
    }

    public function server(): ?Server
    {
        return Server::query()->where('is_local', true)->first();
    }

    /**
     * @return Collection<int, CronJob>
     */
    #[Computed]
    public function jobs(): Collection
    {
        return $this->server()?->cronJobs()->latest('id')->get() ?? collect();
    }

    public function createJob(): void
    {
        $this->validate(['label' => 'required|string|max:255', 'command' => 'required|string|max:2000']);
        $this->validate(['schedule' => ['required', 'string', new ValidCronExpression]]);

        $server = $this->server();
        abort_unless($server !== null && auth()->user()->can('manageCronJobs', $server), 403);

        app(CreateCronJobAction::class)->handle(new CronJobData(
            serverId: $server->id,
            websiteId: null,
            label: $this->label,
            command: $this->command,
            schedule: $this->schedule,
            createdBy: auth()->id(),
        ));

        $this->label = '';
        $this->command = '';
        $this->schedule = '* * * * *';

        unset($this->jobs);

        Notification::make()->title('Cron job created')->success()->send();
    }

    public function toggleJob(int $jobId): void
    {
        $job = $this->findJob($jobId);

        app(ToggleCronJobAction::class)->handle($job);

        unset($this->jobs);
    }

    public function runJobNow(int $jobId): void
    {
        $job = $this->findJob($jobId);

        $result = app(RunCronJobNowAction::class)->handle($job);

        unset($this->jobs);

        Notification::make()
            ->title($result->last_exit_code === 0 ? 'Job ran successfully' : "Job exited with code {$result->last_exit_code}")
            ->success($result->last_exit_code === 0)
            ->warning($result->last_exit_code !== 0)
            ->send();
    }

    public function deleteJob(int $jobId): void
    {
        $job = $this->findJob($jobId);

        app(DeleteCronJobAction::class)->handle($job);

        unset($this->jobs);

        Notification::make()->title('Cron job deleted')->success()->send();
    }

    private function findJob(int $jobId): CronJob
    {
        $server = $this->server();
        abort_unless($server !== null && auth()->user()->can('manageCronJobs', $server), 403);

        return CronJob::query()->where('server_id', $server->id)->findOrFail($jobId);
    }
}
