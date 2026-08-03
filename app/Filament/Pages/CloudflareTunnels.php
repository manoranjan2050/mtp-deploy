<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Cloudflare\CreateTunnelAction;
use App\Actions\Cloudflare\DestroyTunnelAction;
use App\Models\CloudflareTunnel;
use App\Models\Server;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;

class CloudflareTunnels extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCloud;

    protected static ?int $navigationSort = 16;

    protected string $view = 'filament.pages.cloudflare-tunnels';

    #[Validate('required|string|max:255')]
    public string $newTunnelName = '';

    public static function canAccess(): bool
    {
        $server = Server::query()->where('is_local', true)->first();

        return $server !== null && auth()->user()?->can('manageTunnels', $server) === true;
    }

    public function mount(): void
    {
        $server = $this->server();

        abort_unless($server !== null && auth()->user()->can('manageTunnels', $server), 403);
    }

    public function server(): ?Server
    {
        return Server::query()->where('is_local', true)->first();
    }

    /**
     * @return Collection<int, CloudflareTunnel>
     */
    #[Computed]
    public function tunnels(): Collection
    {
        return $this->server()?->cloudflareTunnels()->get() ?? collect();
    }

    public function createTunnel(): void
    {
        $this->validate();

        $server = $this->server();
        abort_unless($server !== null && auth()->user()->can('manageTunnels', $server), 403);

        $result = app(CreateTunnelAction::class)->handle($server, $this->newTunnelName);

        if ($result->successful) {
            $this->newTunnelName = '';
        }

        unset($this->tunnels);

        Notification::make()
            ->title($result->successful ? 'Tunnel created' : 'Tunnel creation failed')
            ->body($result->successful ? null : implode(', ', $result->errors))
            ->success($result->successful)
            ->danger(! $result->successful)
            ->send();
    }

    public function destroyTunnel(int $tunnelId): void
    {
        $server = $this->server();
        abort_unless($server !== null && auth()->user()->can('manageTunnels', $server), 403);

        $tunnel = CloudflareTunnel::query()->where('server_id', $server->id)->findOrFail($tunnelId);

        $result = app(DestroyTunnelAction::class)->handle($tunnel);

        unset($this->tunnels);

        Notification::make()
            ->title($result->successful ? 'Tunnel destroyed' : 'Tunnel destruction failed')
            ->success($result->successful)
            ->danger(! $result->successful)
            ->send();
    }
}
