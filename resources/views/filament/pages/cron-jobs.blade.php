<x-filament-panels::page>
    <x-filament::section heading="Create a cron job">
        <form wire:submit="createJob" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1 block text-sm font-medium">Label</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="label" placeholder="Clear old logs" />
                </x-filament::input.wrapper>
                @error('label') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Command</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="command" placeholder="php artisan logs:clear" />
                </x-filament::input.wrapper>
                @error('command') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Schedule (cron expression)</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="schedule" placeholder="*/5 * * * *" class="font-mono" />
                </x-filament::input.wrapper>
                @error('schedule') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
            </div>
            <x-filament::button type="submit" size="sm">Create</x-filament::button>
        </form>
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            Standard 5-field cron syntax (minute hour day month weekday), e.g. <code>*/5 * * * *</code> = every 5
            minutes, <code>0 3 * * *</code> = daily at 3am. Every enabled job here is synced into this server's
            real system crontab under a clearly-marked block - anything you or another tool added to crontab by
            hand outside that block is left untouched.
        </p>
    </x-filament::section>

    <x-filament::section heading="Cron jobs">
        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full text-start">
                <thead>
                    <tr class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                        <th class="px-3 py-2 text-start">Label</th>
                        <th class="px-3 py-2 text-start">Schedule</th>
                        <th class="px-3 py-2 text-start">Command</th>
                        <th class="px-3 py-2 text-start">Enabled</th>
                        <th class="px-3 py-2 text-start">Last run</th>
                        <th class="px-3 py-2 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($this->jobs() as $jobItem)
                        <tr wire:key="cron-{{ $jobItem->id }}">
                            <td class="px-3 py-2">{{ $jobItem->label }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $jobItem->schedule }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ \Illuminate\Support\Str::limit($jobItem->command, 40) }}</td>
                            <td class="px-3 py-2">
                                <button type="button" wire:click="toggleJob({{ $jobItem->id }})">
                                    <x-filament::badge :color="$jobItem->is_enabled ? 'success' : 'gray'">
                                        {{ $jobItem->is_enabled ? 'Enabled' : 'Disabled' }}
                                    </x-filament::badge>
                                </button>
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">
                                @if ($jobItem->last_run_at)
                                    {{ $jobItem->last_run_at->diffForHumans() }}
                                    (exit {{ $jobItem->last_exit_code }})
                                @else
                                    Never
                                @endif
                            </td>
                            <td class="px-3 py-2 text-end">
                                <button type="button" wire:click="runJobNow({{ $jobItem->id }})" class="text-primary-600 hover:underline">
                                    Run now
                                </button>
                                <button
                                    type="button"
                                    wire:click="deleteJob({{ $jobItem->id }})"
                                    wire:confirm="Delete cron job &quot;{{ $jobItem->label }}&quot;?"
                                    class="ml-3 text-danger-600 hover:underline"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
                                No cron jobs yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
