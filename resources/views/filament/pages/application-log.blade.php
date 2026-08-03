<x-filament-panels::page>
    <x-filament::section heading="MTP Deploy application log">
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-64 flex-1">
                <label class="mb-1 block text-sm font-medium">Search</label>
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="text"
                        wire:model.live.debounce.400ms="query"
                        placeholder="Filter lines, e.g. an exception class or message"
                    />
                </x-filament::input.wrapper>
            </div>
            <x-filament::button type="button" wire:click="refresh" color="gray" size="sm">
                Refresh
            </x-filament::button>
        </div>

        <pre class="mt-4 max-h-[36rem] overflow-auto rounded-lg bg-gray-950 p-4 text-xs leading-5 text-gray-100"><code>{{ $this->logContent() !== '' ? $this->logContent() : '(empty)' }}</code></pre>

        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            {{ $this->path() }} ({{ $this->sizeLabel() }}). Showing the last 300 {{ trim($query) === '' ? 'lines' : 'matching lines' }}.
        </p>
    </x-filament::section>
</x-filament-panels::page>
