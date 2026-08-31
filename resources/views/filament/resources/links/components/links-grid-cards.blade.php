<div class="p-4 sm:p-6">
    @if (isset($records) && $records->isNotEmpty())
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-5">
            @foreach ($records as $record)
                @include('filament.resources.links.components.link-card', ['record' => $record])
            @endforeach
        </div>
    @else
        <div class="flex flex-col items-center justify-center py-16 text-center">
            <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-gray-100 dark:bg-gray-800 text-gray-400 mb-4">
                <x-filament::icon icon="heroicon-o-bookmark-slash" class="w-8 h-8" />
            </div>
            <h3 class="text-base font-bold text-gray-900 dark:text-white">
                {{ __('Aucun lien trouvé') }}
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 max-w-sm mt-1">
                {{ __('Essayez de modifier votre recherche ou vos filtres pour trouver ce que vous cherchez.') }}
            </p>
        </div>
    @endif
</div>
