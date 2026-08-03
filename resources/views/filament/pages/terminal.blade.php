<x-filament-panels::page>
    @vite('resources/js/terminal.js')

    <x-filament::section>
        <div class="flex items-center justify-between gap-4">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Server: <span class="font-medium text-gray-950 dark:text-white">{{ $this->server()?->name }}</span>
                - each command runs as a fresh process against this session's current directory (shown as its
                prompt). This is not a persisted shell environment - exported variables don't carry between
                commands, only <code>cd</code> does.
            </div>
            <x-filament::button size="sm" wire:click="openNewTab">
                New tab
            </x-filament::button>
        </div>
    </x-filament::section>

    @if ($this->openSessions === [])
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">No terminal tabs open. Click "New tab" to start one.</p>
        </x-filament::section>
    @else
        <div class="flex gap-1 border-b border-gray-200 dark:border-white/10">
            @foreach ($this->openSessions as $session)
                <div class="flex items-center gap-1 rounded-t-lg px-3 py-1.5 text-sm {{ $activeSessionId === $session->id ? 'bg-gray-950 text-white' : 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300' }}">
                    <button type="button" wire:click="switchTab({{ $session->id }})">
                        {{ $session->label }}
                    </button>
                    <button type="button" wire:click="closeTab({{ $session->id }})" aria-label="Close tab" class="opacity-70 hover:opacity-100">
                        &times;
                    </button>
                </div>
            @endforeach
        </div>

        @foreach ($this->openSessions as $session)
            <div
                wire:key="terminal-{{ $session->id }}"
                wire:ignore
                @if ($activeSessionId !== $session->id) style="display: none;" @endif
                x-data="{
                    handle: null,
                    init() {
                        this.handle = window.initMtpTerminal(this.$refs.pane, {
                            prompt: @js($this->promptFor($session)),
                            onSubmit: (line) => $wire.call('runCommand', {{ $session->id }}, line),
                        });
                    }
                }"
                x-init="init()"
            >
                <div x-ref="pane" style="height: 420px; background: #0b1120;" class="rounded-lg p-1"></div>
            </div>
        @endforeach
    @endif
</x-filament-panels::page>
