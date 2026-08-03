<x-filament-panels::page>
    <x-filament::section heading="Log source">
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($this->sources() as $label => $path)
                <button
                    type="button"
                    wire:click="selectSource('{{ $label }}')"
                    @class([
                        'rounded-lg px-3 py-1.5 text-sm font-medium',
                        'bg-primary-600 text-white' => $activeSource === $label,
                        'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300' => $activeSource !== $label,
                    ])
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            {{ $this->sources()[$activeSource] ?? '' }}
        </p>
    </x-filament::section>

    <x-filament::section heading="Log content">
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-64 flex-1">
                <label class="mb-1 block text-sm font-medium">Search</label>
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="text"
                        wire:model.live.debounce.400ms="query"
                        placeholder="Filter lines, e.g. an error message or IP address"
                    />
                </x-filament::input.wrapper>
            </div>
            <x-filament::button type="button" wire:click="refresh" color="gray" size="sm">
                Refresh
            </x-filament::button>
        </div>

        <pre class="mt-4 max-h-[32rem] overflow-auto rounded-lg bg-gray-950 p-4 text-xs leading-5 text-gray-100"><code>{{ $this->logContent() !== '' ? $this->logContent() : '(empty)' }}</code></pre>

        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            Showing the last 300 {{ trim($query) === '' ? 'lines' : 'matching lines' }}. Reads the real file directly off disk.
        </p>
    </x-filament::section>
</x-filament-panels::page>
