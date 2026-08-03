<x-filament-panels::page>
    <x-filament::section heading="Active alerts">
        @forelse ($this->activeAlerts() as $alert)
            <div wire:key="alert-{{ $alert->id }}" class="flex items-center justify-between border-b border-gray-100 py-2 last:border-b-0 dark:border-white/5">
                <div>
                    <x-filament::badge :color="$alert->metric->getColor()">{{ $alert->metric->getLabel() }}</x-filament::badge>
                    <span class="ml-2 text-sm">
                        {{ $alert->triggered_value_percent }}% (threshold {{ $alert->threshold_percent }}%) since {{ $alert->triggered_at->diffForHumans() }}
                    </span>
                </div>
                @can('manageMonitoringAlerts', $this->server())
                    <button type="button" wire:click="resolveAlert({{ $alert->id }})" class="text-sm text-primary-600 hover:underline">
                        Resolve
                    </button>
                @endcan
            </div>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">No active alerts.</p>
        @endforelse
    </x-filament::section>

    @can('manageMonitoringAlerts', $this->server())
        <x-filament::section heading="Alert thresholds">
            <form wire:submit="saveThresholds" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1 block text-sm font-medium">CPU % (blank = disabled)</label>
                    <x-filament::input.wrapper>
                        <x-filament::input type="number" wire:model="cpuThreshold" min="1" max="100" />
                    </x-filament::input.wrapper>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Memory % (blank = disabled)</label>
                    <x-filament::input.wrapper>
                        <x-filament::input type="number" wire:model="memoryThreshold" min="1" max="100" />
                    </x-filament::input.wrapper>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Disk % (blank = disabled)</label>
                    <x-filament::input.wrapper>
                        <x-filament::input type="number" wire:model="diskThreshold" min="1" max="100" />
                    </x-filament::input.wrapper>
                </div>
                <x-filament::button type="submit" size="sm">Save</x-filament::button>
            </form>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                An alert is recorded here when a metric stays above its threshold on a capture (every minute).
                Outbound notifications (email/Telegram/etc.) arrive with Module 16.
            </p>
        </x-filament::section>
    @endcan

    <x-filament::section heading="Network bandwidth (bytes/sec, derived from consecutive snapshots)">
        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full text-start">
                <thead>
                    <tr class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                        <th class="px-3 py-2 text-start">Time</th>
                        <th class="px-3 py-2 text-start">RX (bytes/sec)</th>
                        <th class="px-3 py-2 text-start">TX (bytes/sec)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($this->bandwidth() as $row)
                        <tr>
                            <td class="px-3 py-2">{{ $row['recorded_at']->format('H:i:s') }}</td>
                            <td class="px-3 py-2">{{ $row['rx_rate'] ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $row['tx_rate'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
                                Not enough snapshots yet - captured every minute.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section heading="Processes">
        <x-filament::button type="button" wire:click="refreshProcesses" color="gray" size="sm">
            Refresh
        </x-filament::button>

        @if (! $this->processListSupported())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Unsupported on this host ({{ PHP_OS_FAMILY }}) - the process list needs a real `ps` binary, available on Linux servers.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="fi-ta-table w-full text-start">
                    <thead>
                        <tr class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                            <th class="px-3 py-2 text-start">PID</th>
                            <th class="px-3 py-2 text-start">PPID</th>
                            <th class="px-3 py-2 text-start">CPU %</th>
                            <th class="px-3 py-2 text-start">Mem %</th>
                            <th class="px-3 py-2 text-start">Elapsed</th>
                            <th class="px-3 py-2 text-start">Command</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($this->processes() as $process)
                            <tr wire:key="process-{{ $process->pid }}">
                                <td class="px-3 py-2">{{ $process->pid }}</td>
                                <td class="px-3 py-2">{{ $process->ppid }}</td>
                                <td class="px-3 py-2">{{ $process->cpuPercent }}</td>
                                <td class="px-3 py-2">{{ $process->memoryPercent }}</td>
                                <td class="px-3 py-2">{{ $process->elapsedTime }}</td>
                                <td class="px-3 py-2">{{ $process->command }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
                                    No processes read.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
