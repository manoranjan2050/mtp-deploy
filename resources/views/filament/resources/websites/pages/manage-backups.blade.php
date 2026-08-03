<x-filament-panels::page>
    <x-filament::section heading="Schedule">
        <form wire:submit="saveSchedule" class="flex flex-wrap items-end gap-4">
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="backupsEnabled" />
                Enable scheduled daily full backups
            </label>
            <div>
                <label class="mb-1 block text-sm font-medium">Keep last</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="number" wire:model="retentionCount" min="1" max="365" />
                </x-filament::input.wrapper>
            </div>
            <x-filament::button type="submit" size="sm">Save</x-filament::button>
        </form>
    </x-filament::section>

    <x-filament::section heading="Backup now">
        <div class="flex flex-wrap gap-2">
            <x-filament::button size="sm" wire:click="createBackup('files')">Files only</x-filament::button>
            <x-filament::button size="sm" wire:click="createBackup('database')">Database only</x-filament::button>
            <x-filament::button size="sm" color="primary" wire:click="createBackup('full')">Full (files + database)</x-filament::button>
            <x-filament::button size="sm" color="gray" wire:click="createBackup('git')">Git snapshot</x-filament::button>
        </div>
    </x-filament::section>

    <x-filament::section heading="History">
        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full text-start">
                <thead>
                    <tr class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                        <th class="px-3 py-2 text-start">Type</th>
                        <th class="px-3 py-2 text-start">Status</th>
                        <th class="px-3 py-2 text-start">Size</th>
                        <th class="px-3 py-2 text-start">Created</th>
                        <th class="px-3 py-2 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($this->backups() as $backupItem)
                        <tr wire:key="backup-{{ $backupItem->id }}">
                            <td class="px-3 py-2">{{ $backupItem->type->getLabel() }}</td>
                            <td class="px-3 py-2">
                                <x-filament::badge :color="$backupItem->status->getColor()">{{ $backupItem->status->getLabel() }}</x-filament::badge>
                            </td>
                            <td class="px-3 py-2">{{ $backupItem->size_bytes ? number_format($backupItem->size_bytes / 1024 / 1024, 2).' MB' : '—' }}</td>
                            <td class="px-3 py-2">{{ $backupItem->created_at->diffForHumans() }}</td>
                            <td class="px-3 py-2 text-end">
                                @if ($backupItem->status->value === 'success')
                                    <button
                                        type="button"
                                        wire:click="restoreBackup({{ $backupItem->id }})"
                                        wire:confirm="Restore this backup? This will overwrite the current files/database."
                                        class="text-primary-600 hover:underline"
                                    >
                                        Restore
                                    </button>
                                @endif
                                <button
                                    type="button"
                                    wire:click="deleteBackup({{ $backupItem->id }})"
                                    wire:confirm="Delete this backup?"
                                    class="ml-3 text-danger-600 hover:underline"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
                                No backups yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
