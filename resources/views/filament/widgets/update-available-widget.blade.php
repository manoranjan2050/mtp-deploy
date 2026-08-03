@if ($this->isUpdateAvailable())
    <x-filament-widgets::widget>
        <x-filament::section>
            <div class="flex items-center justify-between">
                <p class="text-sm">
                    <span class="font-medium text-warning-600 dark:text-warning-400">Update available</span>
                    <span class="text-gray-500 dark:text-gray-400"> - a newer version is on GitHub.</span>
                </p>
                <a href="{{ route('filament.admin.pages.about') }}" class="text-sm text-primary-600 hover:underline">View details</a>
            </div>
        </x-filament::section>
    </x-filament-widgets::widget>
@endif
