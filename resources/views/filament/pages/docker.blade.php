<x-filament-panels::page>
    <x-filament::section heading="Containers">
        @if ($this->containersUnavailable())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Could not reach the Docker Engine API at {{ config('services.docker.base_url') }}.
                Confirm Docker is running and reachable from this app.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="fi-ta-table w-full text-start">
                    <thead>
                        <tr class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                            <th class="px-3 py-2 text-start">Name</th>
                            <th class="px-3 py-2 text-start">Image</th>
                            <th class="px-3 py-2 text-start">State</th>
                            <th class="px-3 py-2 text-start">Status</th>
                            <th class="px-3 py-2 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($this->containers() as $container)
                            <tr wire:key="container-{{ $container->id }}">
                                <td class="px-3 py-2">{{ $container->name }}</td>
                                <td class="px-3 py-2">{{ $container->image }}</td>
                                <td class="px-3 py-2">{{ $container->state }}</td>
                                <td class="px-3 py-2">{{ $container->status }}</td>
                                <td class="px-3 py-2 text-end">
                                    <button type="button" wire:click="startContainer('{{ $container->id }}')" class="text-success-600 hover:underline">Start</button>
                                    <button type="button" wire:click="stopContainer('{{ $container->id }}')" class="ml-2 text-warning-600 hover:underline">Stop</button>
                                    <button type="button" wire:click="restartContainer('{{ $container->id }}')" class="ml-2 text-primary-600 hover:underline">Restart</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
                                    No containers.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section heading="Pull an image">
        <form wire:submit="pullImage" class="flex flex-wrap items-end gap-3">
            <div class="min-w-64 flex-1">
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="imageName" placeholder="e.g. nginx:latest" />
                </x-filament::input.wrapper>
                @error('imageName') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
            </div>
            <x-filament::button type="submit" size="sm">Pull</x-filament::button>
        </form>
    </x-filament::section>

    <x-filament::section heading="Images">
        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full text-start">
                <thead>
                    <tr class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                        <th class="px-3 py-2 text-start">Tag</th>
                        <th class="px-3 py-2 text-start">Size</th>
                        <th class="px-3 py-2 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($this->images() as $image)
                        <tr wire:key="image-{{ $image->id }}">
                            <td class="px-3 py-2">{{ $image->tag }}</td>
                            <td class="px-3 py-2">{{ number_format($image->sizeBytes / 1048576, 1) }} MB</td>
                            <td class="px-3 py-2 text-end">
                                <button
                                    type="button"
                                    wire:click="removeImage('{{ $image->id }}')"
                                    wire:confirm="Remove this image?"
                                    class="text-danger-600 hover:underline"
                                >
                                    Remove
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
                                No images.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
