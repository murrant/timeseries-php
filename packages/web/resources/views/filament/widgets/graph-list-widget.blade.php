<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Quick Links
        </x-slot>

        <div class="space-y-2">
            @foreach ($this->getGraphs() as $graph)
                <div class="flex items-start gap-3 rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                    <div class="flex-shrink-0 mt-1">
                        <x-filament::icon
                            icon="heroicon-o-link"
                            class="w-5 h-5 text-gray-400"
                        />
                    </div>
                    <div class="flex-1 min-w-0">
                        <a href="{{ route('data.graph.show', $graph->id) }}" target="_blank" class="font-medium text-primary-600 dark:text-primary-400 hover:underline">
                            {{ $graph->title }}
                        </a>
                        @if (isset($graph->description))
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                {{ $graph->description }}
                            </p>
                        @endif
                    </div>
                    <div class="flex-shrink-0">
                        <x-filament::icon
                            icon="heroicon-o-arrow-top-right-on-square"
                            class="w-4 h-4 text-gray-400"
                        />
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
