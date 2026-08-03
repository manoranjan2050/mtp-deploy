<x-filament-panels::page>
    @php $zone = $this->zone(); @endphp

    @if ($zone === null)
        <x-filament::section heading="Connect Cloudflare">
            <form wire:submit="connect" class="flex flex-col gap-4 max-w-md">
                <div>
                    <label class="mb-1 block text-sm font-medium">Zone ID</label>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model="zoneId" placeholder="023e105f4ecef8ad9ca31a8372d0c353" />
                    </x-filament::input.wrapper>
                    @error('zoneId') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">API Token</label>
                    <x-filament::input.wrapper>
                        <x-filament::input type="password" wire:model="apiToken" />
                    </x-filament::input.wrapper>
                    @error('apiToken') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-filament::button type="submit">Connect</x-filament::button>
                </div>
            </form>
        </x-filament::section>
    @else
        <x-filament::section heading="Zone">
            <div class="flex flex-col gap-4">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Zone ID: <span class="font-mono text-gray-950 dark:text-white">{{ $zone->zone_id }}</span>
                    @if ($zone->last_synced_at)
                        - last synced {{ $zone->last_synced_at->diffForHumans() }}
                    @endif
                </div>

                <div class="flex items-end gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium">SSL/TLS mode</label>
                        <select
                            wire:change="updateSslMode($event.target.value)"
                            class="fi-select-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900"
                        >
                            @foreach (\App\Enums\CloudflareSslMode::cases() as $mode)
                                <option value="{{ $mode->value }}" @selected($zone->ssl_mode === $mode)>{{ $mode->getLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-filament::button size="sm" color="gray" wire:click="purgeCache">
                        Purge cache
                    </x-filament::button>
                    <x-filament::button
                        size="sm"
                        color="danger"
                        wire:click="disconnect"
                        wire:confirm="Disconnect this Cloudflare zone from this website?"
                    >
                        Disconnect
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section heading="DNS records">
            <form wire:submit="createDnsRecord" class="mb-4 flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-xs font-medium">Type</label>
                    <select wire:model="recordType" class="fi-select-input rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
                        <option value="A">A</option>
                        <option value="AAAA">AAAA</option>
                        <option value="CNAME">CNAME</option>
                        <option value="TXT">TXT</option>
                        <option value="MX">MX</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium">Name</label>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model="recordName" placeholder="app.example.com" />
                    </x-filament::input.wrapper>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium">Content</label>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model="recordContent" placeholder="1.2.3.4" />
                    </x-filament::input.wrapper>
                </div>
                <label class="mb-2 flex items-center gap-1 text-xs">
                    <input type="checkbox" wire:model="recordProxied" />
                    Proxied
                </label>
                <x-filament::button type="submit" size="sm">Add record</x-filament::button>
            </form>

            <div class="overflow-x-auto">
                <table class="fi-ta-table w-full text-start">
                    <thead>
                        <tr class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                            <th class="px-3 py-2 text-start">Type</th>
                            <th class="px-3 py-2 text-start">Name</th>
                            <th class="px-3 py-2 text-start">Content</th>
                            <th class="px-3 py-2 text-start">Proxied</th>
                            <th class="px-3 py-2 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($this->dnsRecords() as $recordItem)
                            <tr wire:key="dns-{{ $recordItem->id }}">
                                <td class="px-3 py-2">{{ $recordItem->type }}</td>
                                <td class="px-3 py-2">{{ $recordItem->name }}</td>
                                <td class="px-3 py-2">{{ $recordItem->content }}</td>
                                <td class="px-3 py-2">{{ $recordItem->proxied ? 'Yes' : 'No' }}</td>
                                <td class="px-3 py-2 text-end">
                                    <button
                                        type="button"
                                        wire:click="deleteDnsRecord('{{ $recordItem->id }}')"
                                        wire:confirm="Delete this DNS record?"
                                        class="text-danger-600 hover:underline"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
                                    No DNS records found (or the last sync failed - see notifications).
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
