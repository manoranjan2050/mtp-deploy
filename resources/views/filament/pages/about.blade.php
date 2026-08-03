<x-filament-panels::page>
    @if ($this->isUpdateAvailable())
        <x-filament::section>
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-medium text-warning-600 dark:text-warning-400">An update is available</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        This installation is behind the {{ config('mtp.github_branch') }} branch on GitHub.
                        Pull the latest changes and re-run the relevant steps from INSTALL.md
                        (<code>git pull</code>, <code>composer install</code>, <code>npm run build</code>,
                        <code>php artisan migrate</code>).
                    </p>
                </div>
                <x-filament::button tag="a" href="{{ $this->getRepoUrl() }}/commits/{{ config('mtp.github_branch') }}" target="_blank" size="sm">
                    View changes
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif

    <x-filament::section heading="MTP Deploy">
        <div class="flex flex-col gap-2 text-sm">
            <p class="text-gray-600 dark:text-gray-300">
                A modern, self-hosted server management and deployment platform for Laravel, PHP, and static
                websites - an open alternative to Laravel Forge/Ploi/CloudPanel, built to run on your own server.
            </p>
            <div class="grid grid-cols-2 gap-x-6 gap-y-1 sm:grid-cols-3">
                <div><span class="text-gray-500 dark:text-gray-400">Repository:</span> <a href="{{ $this->getRepoUrl() }}" target="_blank" class="text-primary-600 hover:underline">{{ config('mtp.github_repo') }}</a></div>
                <div><span class="text-gray-500 dark:text-gray-400">Branch:</span> {{ config('mtp.github_branch') }}</div>
                <div><span class="text-gray-500 dark:text-gray-400">Installed commit:</span> <code class="text-xs">{{ $this->getCurrentCommit() ? substr($this->getCurrentCommit(), 0, 10) : 'unknown' }}</code></div>
                <div><span class="text-gray-500 dark:text-gray-400">Laravel:</span> {{ app()->version() }}</div>
                <div><span class="text-gray-500 dark:text-gray-400">PHP:</span> {{ PHP_VERSION }}</div>
                <div><span class="text-gray-500 dark:text-gray-400">License:</span> Proprietary</div>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section heading="Tech stack">
        <div class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-3">
            <div>Laravel 12</div>
            <div>Filament v5</div>
            <div>Livewire v4</div>
            <div>Tailwind CSS</div>
            <div>Alpine.js</div>
            <div>MariaDB / MySQL</div>
            <div>Redis</div>
            <div>nginx</div>
            <div>Supervisor</div>
            <div>Cloudflare API</div>
            <div>xterm.js</div>
            <div>Symfony Process</div>
        </div>
    </x-filament::section>

    <x-filament::section heading="Developer">
        <div class="flex flex-col gap-1 text-sm">
            <div><span class="text-gray-500 dark:text-gray-400">GitHub:</span> <a href="https://github.com/manoranjan2050" target="_blank" class="text-primary-600 hover:underline">@manoranjan2050</a></div>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Edit <code>resources/views/filament/pages/about.blade.php</code> to add your name, bio, and any
                other credits you'd like shown here.
            </p>
        </div>
    </x-filament::section>

    <x-filament::section heading="Declaration">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            This software is provided as-is, without warranty of any kind. You are responsible for securing,
            backing up, and maintaining any server you install it on - see
            <a href="{{ $this->getRepoUrl() }}/blob/main/docs/Security.md" target="_blank" class="text-primary-600 hover:underline">docs/Security.md</a>
            for the security model this panel follows.
        </p>
    </x-filament::section>
</x-filament-panels::page>
