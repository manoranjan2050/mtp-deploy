<x-filament-panels::page>
    <x-filament::section heading="Create a queue worker">
        <form wire:submit="createWorker" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1 block text-sm font-medium">Connection</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="connection" placeholder="database" />
                </x-filament::input.wrapper>
                @error('connection') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Queue</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="queue" placeholder="default" />
                </x-filament::input.wrapper>
                @error('queue') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Processes</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="number" wire:model="processes" min="1" max="20" />
                </x-filament::input.wrapper>
                @error('processes') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
            </div>
            <x-filament::button type="submit" size="sm">Create</x-filament::button>
        </form>
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            Creates a real Supervisor program (<code>php artisan queue:work {{ '{connection}' }} --queue={{ '{queue}' }}</code>)
            running from this website's own document root, supervised so it restarts automatically if it crashes.
        </p>
    </x-filament::section>

    <x-filament::section heading="Queue workers">
        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full text-start">
                <thead>
                    <tr class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                        <th class="px-3 py-2 text-start">Connection</th>
                        <th class="px-3 py-2 text-start">Queue</th>
                        <th class="px-3 py-2 text-start">Processes</th>
                        <th class="px-3 py-2 text-start">Status</th>
                        <th class="px-3 py-2 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($this->workers() as $workerItem)
                        <tr wire:key="worker-{{ $workerItem->id }}">
                            <td class="px-3 py-2">{{ $workerItem->connection }}</td>
                            <td class="px-3 py-2">{{ $workerItem->queue }}</td>
                            <td class="px-3 py-2">{{ $workerItem->processes }}</td>
                            <td class="px-3 py-2">
                                <x-filament::badge :color="$workerItem->status->getColor()">{{ $workerItem->status->getLabel() }}</x-filament::badge>
                            </td>
                            <td class="px-3 py-2 text-end">
                                <button type="button" wire:click="startWorker({{ $workerItem->id }})" class="text-success-600 hover:underline">Start</button>
                                <button type="button" wire:click="stopWorker({{ $workerItem->id }})" class="ml-2 text-warning-600 hover:underline">Stop</button>
                                <button type="button" wire:click="restartWorker({{ $workerItem->id }})" class="ml-2 text-primary-600 hover:underline">Restart</button>
                                <button
                                    type="button"
                                    wire:click="deleteWorker({{ $workerItem->id }})"
                                    wire:confirm="Delete this queue worker?"
                                    class="ml-2 text-danger-600 hover:underline"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
                                No queue workers yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
