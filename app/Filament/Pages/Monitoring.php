<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\DTOs\Monitoring\ProcessData;
use App\Models\Alert;
use App\Models\Server;
use App\Models\SystemMetricSnapshot;
use App\Services\AiAssistant\AiAssistantService;
use App\Services\Monitoring\ProcessListService;
use App\Services\System\SystemMetricsService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;

class Monitoring extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 14;

    protected string $view = 'filament.pages.monitoring';

    #[Validate('nullable|integer|min:1|max:100')]
    public ?int $cpuThreshold = null;

    #[Validate('nullable|integer|min:1|max:100')]
    public ?int $memoryThreshold = null;

    #[Validate('nullable|integer|min:1|max:100')]
    public ?int $diskThreshold = null;

    public function mount(): void
    {
        $server = $this->server();

        $this->cpuThreshold = $server?->cpu_alert_threshold;
        $this->memoryThreshold = $server?->memory_alert_threshold;
        $this->diskThreshold = $server?->disk_alert_threshold;
    }

    public function server(): ?Server
    {
        return Server::query()->where('is_local', true)->first();
    }

    /**
     * @return Collection<int, Alert>
     */
    #[Computed]
    public function activeAlerts(): Collection
    {
        return $this->server()?->alerts()->whereNull('resolved_at')->latest('triggered_at')->get() ?? collect();
    }

    /**
     * @return Collection<int, Alert>
     */
    #[Computed]
    public function recentResolvedAlerts(): Collection
    {
        return $this->server()?->alerts()->whereNotNull('resolved_at')->latest('resolved_at')->limit(10)->get() ?? collect();
    }

    /**
     * @return \Illuminate\Support\Collection<int, ProcessData>
     */
    #[Computed]
    public function processes(): \Illuminate\Support\Collection
    {
        return app(ProcessListService::class)->list();
    }

    public function processListSupported(): bool
    {
        return app(ProcessListService::class)->isSupported();
    }

    /**
     * @return array<int, array{recorded_at: Carbon, rx_rate: ?float, tx_rate: ?float}>
     */
    #[Computed]
    public function bandwidth(): array
    {
        $snapshots = SystemMetricSnapshot::query()
            ->where('is_supported', true)
            ->whereNotNull('network_rx_bytes')
            ->latest('recorded_at')
            ->limit(21)
            ->get()
            ->reverse()
            ->values();

        $rows = [];

        for ($i = 1; $i < $snapshots->count(); $i++) {
            $previous = $snapshots[$i - 1];
            $current = $snapshots[$i];

            $seconds = $current->recorded_at->diffInSeconds($previous->recorded_at);

            if ($seconds <= 0) {
                continue;
            }

            $rxDelta = $current->network_rx_bytes - $previous->network_rx_bytes;
            $txDelta = $current->network_tx_bytes - $previous->network_tx_bytes;

            $rows[] = [
                'recorded_at' => $current->recorded_at,
                'rx_rate' => $rxDelta >= 0 ? round($rxDelta / $seconds, 1) : null,
                'tx_rate' => $txDelta >= 0 ? round($txDelta / $seconds, 1) : null,
            ];
        }

        return array_reverse($rows);
    }

    public function refreshProcesses(): void
    {
        unset($this->processes);
    }

    public function saveThresholds(): void
    {
        $this->validate();

        $server = $this->server();
        abort_unless($server !== null && auth()->user()->can('manageMonitoringAlerts', $server), 403);

        $server->update([
            'cpu_alert_threshold' => $this->cpuThreshold,
            'memory_alert_threshold' => $this->memoryThreshold,
            'disk_alert_threshold' => $this->diskThreshold,
        ]);

        Notification::make()->title('Alert thresholds saved')->success()->send();
    }

    public function resolveAlert(int $alertId): void
    {
        $server = $this->server();
        abort_unless($server !== null && auth()->user()->can('manageMonitoringAlerts', $server), 403);

        $alert = Alert::query()->where('server_id', $server->id)->whereNull('resolved_at')->findOrFail($alertId);
        $alert->update(['resolved_at' => now()]);

        unset($this->activeAlerts, $this->recentResolvedAlerts);

        Notification::make()->title('Alert resolved')->success()->send();
    }

    public function canUseAiAssistant(): bool
    {
        return auth()->user()->can('use ai assistant');
    }

    public function aiHealthSummary(): void
    {
        abort_unless($this->canUseAiAssistant(), 403);

        $metrics = app(SystemMetricsService::class)->capture();
        $alerts = $this->activeAlerts()->map(fn (Alert $alert): string => "{$alert->metric->getLabel()}: {$alert->triggered_value_percent}% (threshold {$alert->threshold_percent}%)")->implode("\n") ?: 'none';

        $prompt = $metrics->isSupported
            ? "CPU: {$metrics->cpuUsagePercent}%\nMemory: {$metrics->memoryUsagePercent()}%\nDisk: {$metrics->diskUsagePercent()}%\nLoad: {$metrics->load1min}/{$metrics->load5min}/{$metrics->load15min}\nActive alerts:\n{$alerts}"
            : 'Live metrics are unsupported on this host ('.PHP_OS_FAMILY.").\nActive alerts:\n{$alerts}";

        $result = app(AiAssistantService::class)->ask(
            'You are a server operations assistant. Summarize this server\'s current health in 2-3 plain-English sentences for a non-expert. Flag anything concerning.',
            $prompt,
        );

        Notification::make()
            ->title($result->successful ? 'AI health summary' : 'AI Assistant unavailable')
            ->body($result->successful ? $result->text : $result->error)
            ->success($result->successful)
            ->warning(! $result->successful)
            ->persistent()
            ->send();
    }
}
