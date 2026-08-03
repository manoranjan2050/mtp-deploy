<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Docker\PullImageAction;
use App\Actions\Docker\RemoveImageAction;
use App\Actions\Docker\RestartContainerAction;
use App\Actions\Docker\StartContainerAction;
use App\Actions\Docker\StopContainerAction;
use App\DTOs\Docker\DockerApiResult;
use App\DTOs\Docker\DockerContainerData;
use App\DTOs\Docker\DockerImageData;
use App\Models\Server;
use App\Services\Docker\DockerApiClient;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;

class Docker extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?int $navigationSort = 19;

    protected string $view = 'filament.pages.docker';

    #[Validate('required|string|max:255')]
    public string $imageName = '';

    public static function canAccess(): bool
    {
        $server = Server::query()->where('is_local', true)->first();

        return $server !== null && auth()->user()?->can('manageDocker', $server) === true;
    }

    public function mount(): void
    {
        $server = $this->server();

        abort_unless($server !== null && auth()->user()->can('manageDocker', $server), 403);
    }

    public function server(): ?Server
    {
        return Server::query()->where('is_local', true)->first();
    }

    #[Computed]
    public function containersResult(): DockerApiResult
    {
        return app(DockerApiClient::class)->listContainers();
    }

    /**
     * @return Collection<int, DockerContainerData>
     */
    public function containers(): Collection
    {
        $result = $this->containersResult();

        return $result->successful ? collect($result->data) : collect();
    }

    public function containersUnavailable(): bool
    {
        return ! $this->containersResult()->successful;
    }

    /**
     * @return Collection<int, DockerImageData>
     */
    #[Computed]
    public function images(): Collection
    {
        $result = app(DockerApiClient::class)->listImages();

        return $result->successful ? collect($result->data) : collect();
    }

    public function startContainer(string $containerId): void
    {
        $this->runContainerAction(app(StartContainerAction::class)->handle($containerId), 'started');
    }

    public function stopContainer(string $containerId): void
    {
        $this->runContainerAction(app(StopContainerAction::class)->handle($containerId), 'stopped');
    }

    public function restartContainer(string $containerId): void
    {
        $this->runContainerAction(app(RestartContainerAction::class)->handle($containerId), 'restarted');
    }

    public function pullImage(): void
    {
        $this->validate();

        $result = app(PullImageAction::class)->handle($this->imageName);

        $this->imageName = '';
        unset($this->images);

        Notification::make()
            ->title($result->successful ? 'Image pulled' : 'Pull failed')
            ->body($result->successful ? null : implode(', ', $result->errors))
            ->success($result->successful)
            ->danger(! $result->successful)
            ->send();
    }

    public function removeImage(string $imageId): void
    {
        $result = app(RemoveImageAction::class)->handle($imageId);

        unset($this->images);

        Notification::make()
            ->title($result->successful ? 'Image removed' : 'Remove failed')
            ->body($result->successful ? null : implode(', ', $result->errors))
            ->success($result->successful)
            ->danger(! $result->successful)
            ->send();
    }

    private function runContainerAction(DockerApiResult $result, string $verb): void
    {
        unset($this->containersResult);

        Notification::make()
            ->title($result->successful ? "Container {$verb}" : "Failed to {$verb} container")
            ->body($result->successful ? null : implode(', ', $result->errors))
            ->success($result->successful)
            ->danger(! $result->successful)
            ->send();
    }
}
