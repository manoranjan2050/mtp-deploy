<x-filament-widgets::widget>
    <x-filament::section heading="Services">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            <div class="flex flex-col gap-1">
                <span class="text-sm text-gray-500 dark:text-gray-400">PHP</span>
                <span class="text-sm font-medium">{{ $this->getPhpVersion() }}</span>
            </div>

            @foreach ($this->getServiceStatuses() as $service => $status)
                <div class="flex flex-col gap-1">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ str($service)->replace('_', ' ')->title() }}
                    </span>
                    <x-filament::badge :color="$status->getColor()">
                        {{ $status->getLabel() }}
                    </x-filament::badge>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
