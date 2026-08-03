<x-filament-panels::page>
    @php $current = $this->currentCertificate(); @endphp

    <x-filament::section heading="Current certificate">
        @if ($current === null)
            <p class="text-sm text-gray-500 dark:text-gray-400">No active certificate for this website.</p>
        @else
            <div class="flex flex-col gap-2 text-sm">
                <div>
                    Type: <x-filament::badge :color="$current->type->getColor()">{{ $current->type->getLabel() }}</x-filament::badge>
                    Status: <x-filament::badge :color="$current->status->getColor()">{{ $current->status->getLabel() }}</x-filament::badge>
                </div>
                <div class="text-gray-500 dark:text-gray-400">Domains: {{ implode(', ', $current->domains) }}</div>
                @if ($current->expires_at)
                    <div class="text-gray-500 dark:text-gray-400">Expires {{ $current->expires_at->diffForHumans() }}</div>
                @endif
                <div class="mt-2 flex gap-2">
                    @if ($current->type->value === 'lets_encrypt')
                        <x-filament::button size="sm" wire:click="renew({{ $current->id }})">Renew now</x-filament::button>
                    @endif
                    <x-filament::button size="sm" color="danger" wire:click="revoke({{ $current->id }})" wire:confirm="Revoke this certificate?">
                        Revoke
                    </x-filament::button>
                </div>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section heading="Issue a Let's Encrypt certificate">
        <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">
            This cannot be fully verified in this dev environment - Let's Encrypt validates domain control by
            connecting back to a public IP/domain (see docs/Security.md). Configured to talk to the ACME
            <strong>staging</strong> directory by default.
        </p>
        <form wire:submit="issue" class="flex flex-col gap-3 max-w-lg">
            <div>
                <label class="mb-1 block text-sm font-medium">Domains (comma-separated)</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="domainsInput" placeholder="example.com, www.example.com" />
                </x-filament::input.wrapper>
                @error('domainsInput') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="useDnsChallenge" />
                Use DNS-01 challenge via Cloudflare (required for wildcard domains, e.g. <code>*.example.com</code>)
            </label>
            <div>
                <x-filament::button type="submit" size="sm">Issue certificate</x-filament::button>
            </div>
        </form>
    </x-filament::section>

    <x-filament::section heading="Upload a custom certificate">
        <form wire:submit="uploadCustom" class="flex flex-col gap-3 max-w-lg">
            <div>
                <label class="mb-1 block text-sm font-medium">Certificate (PEM, full chain)</label>
                <textarea wire:model="uploadCertificatePem" rows="6" class="w-full rounded-lg border-gray-300 font-mono text-xs dark:border-gray-600 dark:bg-gray-900"></textarea>
                @error('uploadCertificatePem') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Private key (PEM)</label>
                <textarea wire:model="uploadPrivateKeyPem" rows="6" class="w-full rounded-lg border-gray-300 font-mono text-xs dark:border-gray-600 dark:bg-gray-900"></textarea>
                @error('uploadPrivateKeyPem') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-filament::button type="submit" size="sm" color="gray">Upload certificate</x-filament::button>
            </div>
        </form>
    </x-filament::section>

    @if ($this->history()->isNotEmpty())
        <x-filament::section heading="History">
            <div class="overflow-x-auto">
                <table class="fi-ta-table w-full text-start">
                    <thead>
                        <tr class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                            <th class="px-3 py-2 text-start">Type</th>
                            <th class="px-3 py-2 text-start">Domains</th>
                            <th class="px-3 py-2 text-start">Status</th>
                            <th class="px-3 py-2 text-start">Expires</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($this->history() as $certificateItem)
                            <tr wire:key="cert-{{ $certificateItem->id }}">
                                <td class="px-3 py-2">{{ $certificateItem->type->getLabel() }}</td>
                                <td class="px-3 py-2">{{ implode(', ', $certificateItem->domains) }}</td>
                                <td class="px-3 py-2">
                                    <x-filament::badge :color="$certificateItem->status->getColor()">{{ $certificateItem->status->getLabel() }}</x-filament::badge>
                                </td>
                                <td class="px-3 py-2">{{ $certificateItem->expires_at?->diffForHumans() ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
