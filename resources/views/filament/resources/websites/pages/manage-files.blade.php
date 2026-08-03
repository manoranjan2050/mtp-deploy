<x-filament-panels::page>
    {{-- Plain-Blade breadcrumb trail (not <x-filament::breadcrumbs>) because
         navigation here is client-side Livewire state, not routed URLs. --}}
    <nav class="flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
        @foreach ($this->getBreadcrumbTrail() as $index => $crumb)
            @if (! $loop->first)
                <span aria-hidden="true">/</span>
            @endif

            @if ($loop->last)
                <span class="font-medium text-gray-950 dark:text-white">{{ $crumb['label'] }}</span>
            @else
                <button
                    type="button"
                    wire:click="navigateTo('{{ $crumb['path'] }}')"
                    class="hover:text-primary-600 dark:hover:text-primary-400 hover:underline"
                >
                    {{ $crumb['label'] }}
                </button>
            @endif
        @endforeach
    </nav>

    <x-filament::section heading="Upload & create">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
            <form wire:submit="upload" class="flex items-end gap-2">
                <div>
                    <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium">Upload file</label>
                    {{-- Explicit file: classes are required here - Tailwind's
                         preflight reset strips the native file input's
                         "Choose File" button styling entirely, which without
                         this leaves only the "No file chosen" text visible
                         with no clickable button to open the file picker. --}}
                    <input
                        type="file"
                        wire:model="newUpload"
                        class="block text-sm text-gray-950 dark:text-white file:mr-3 file:rounded-md file:border-0 file:bg-primary-600 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white hover:file:bg-primary-500"
                    />
                    @error('newUpload') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                </div>
                <x-filament::button type="submit" size="sm" wire:loading.attr="disabled" wire:target="newUpload,upload">
                    Upload
                </x-filament::button>
            </form>

            <form wire:submit="createFolder" class="flex items-end gap-2">
                <div>
                    <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium">New folder</label>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model="newFolderName" placeholder="folder-name" />
                    </x-filament::input.wrapper>
                    @error('newFolderName') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                </div>
                <x-filament::button type="submit" size="sm" color="gray">
                    Create folder
                </x-filament::button>
            </form>
        </div>
    </x-filament::section>

    @if ($editingPath !== null)
        <x-filament::section :heading="'Editing: '.$editingPath">
            <form wire:submit="saveEditing" class="flex flex-col gap-3">
                <textarea
                    wire:model="editingContents"
                    rows="20"
                    spellcheck="false"
                    class="w-full rounded-lg border-gray-300 font-mono text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                ></textarea>
                <div class="flex gap-2">
                    <x-filament::button type="submit" size="sm">Save</x-filament::button>
                    <x-filament::button type="button" size="sm" color="gray" wire:click="cancelEditing">Cancel</x-filament::button>
                </div>
            </form>
        </x-filament::section>
    @endif

    <x-filament::section heading="Files">
        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full text-start">
                <thead>
                    <tr class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                        <th class="px-3 py-2 text-start">Name</th>
                        <th class="px-3 py-2 text-start">Size</th>
                        <th class="px-3 py-2 text-start">Modified</th>
                        <th class="px-3 py-2 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($this->entries as $entry)
                        <tr wire:key="entry-{{ $entry->relativePath }}">
                            <td class="px-3 py-2">
                                @if ($renamingPath === $entry->relativePath)
                                    <form wire:submit="confirmRename" class="flex items-center gap-2">
                                        <x-filament::input type="text" wire:model="renamingName" />
                                        <x-filament::button type="submit" size="xs">Save</x-filament::button>
                                        <x-filament::button type="button" size="xs" color="gray" wire:click="cancelRenaming">Cancel</x-filament::button>
                                    </form>
                                @elseif ($entry->isDirectory)
                                    <button
                                        type="button"
                                        wire:click="navigateTo('{{ $entry->relativePath }}')"
                                        class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                    >
                                        📁 {{ $entry->name }}
                                    </button>
                                @else
                                    <span>{{ $entry->name }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-gray-500 dark:text-gray-400">
                                {{ $entry->isDirectory ? '—' : number_format($entry->sizeBytes / 1024, 1).' KB' }}
                            </td>
                            <td class="px-3 py-2 text-gray-500 dark:text-gray-400">
                                {{ \Illuminate\Support\Carbon::createFromTimestamp($entry->modifiedAt)->diffForHumans() }}
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex justify-end gap-2 text-sm">
                                    @if (! $entry->isDirectory)
                                        <button type="button" wire:click="download('{{ $entry->relativePath }}')" class="text-gray-500 hover:text-primary-600">Download</button>

                                        @if ($entry->isEditableText())
                                            <button type="button" wire:click="startEditing('{{ $entry->relativePath }}')" class="text-gray-500 hover:text-primary-600">Edit</button>
                                        @endif

                                        @if (str_ends_with(strtolower($entry->name), '.zip'))
                                            <button type="button" wire:click="unzipItem('{{ $entry->relativePath }}')" class="text-gray-500 hover:text-primary-600">Unzip</button>
                                        @endif
                                    @endif

                                    <button type="button" wire:click="zipItem('{{ $entry->relativePath }}')" class="text-gray-500 hover:text-primary-600">Zip</button>
                                    <button type="button" wire:click="startRenaming('{{ $entry->relativePath }}')" class="text-gray-500 hover:text-primary-600">Rename</button>
                                    <button
                                        type="button"
                                        wire:click="delete('{{ $entry->relativePath }}')"
                                        wire:confirm="Delete {{ $entry->name }}? This cannot be undone."
                                        class="text-danger-600 hover:underline"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
                                This directory is empty.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
