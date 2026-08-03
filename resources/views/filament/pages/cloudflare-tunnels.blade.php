<x-filament-panels::page>
    <x-filament::section heading="Create a tunnel">
        <form wire:submit="createTunnel" class="flex items-end gap-2">
            <div>
                <label class="mb-1 block text-sm font-medium">Name</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="newTunnelName" placeholder="prod-tunnel" />
                </x-filament::input.wrapper>
                @error('newTunnelName') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
            </div>
            <x-filament::button type="submit" size="sm">Create tunnel</x-filament::button>
        </form>
    </x-filament::section>

    <x-filament::section heading="Tunnels">
        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
            Creating a tunnel here registers it with Cloudflare, but no traffic flows through it until a real
            <code>cloudflared tunnel run</code> connector process is started on this server - that step isn't
            automated yet (see docs/Roadmap.md).
        </p>

        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full text-start">
                <thead>
                    <tr class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                        <th class="px-3 py-2 text-start">Name</th>
                        <th class="px-3 py-2 text-start">Tunnel ID</th>
                        <th class="px-3 py-2 text-start">Status</th>
                        <th class="px-3 py-2 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($this->tunnels() as $tunnel)
                        <tr wire:key="tunnel-{{ $tunnel->id }}">
                            <td class="px-3 py-2">{{ $tunnel->name }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $tunnel->cloudflare_tunnel_id }}</td>
                            <td class="px-3 py-2">
                                <x-filament::badge :color="$tunnel->status->getColor()">
                                    {{ $tunnel->status->getLabel() }}
                                </x-filament::badge>
                            </td>
                            <td class="px-3 py-2 text-end">
                                <button
                                    type="button"
                                    wire:click="destroyTunnel({{ $tunnel->id }})"
                                    wire:confirm="Destroy tunnel &quot;{{ $tunnel->name }}&quot;?"
                                    class="text-danger-600 hover:underline"
                                >
                                    Destroy
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
                                No tunnels yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
