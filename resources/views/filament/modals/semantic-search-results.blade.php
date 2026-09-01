<div class="space-y-4">
    <div class="p-4 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <x-filament::icon icon="heroicon-m-sparkles" class="w-5 h-5 text-purple-600 dark:text-purple-400" />
            <span class="text-xs font-semibold text-purple-900 dark:text-purple-200">
                {{ __('Requête :') }} <strong class="underline decoration-purple-400">"{{ $query }}"</strong>
            </span>
        </div>
        <span class="px-2.5 py-0.5 text-xs font-bold text-purple-700 bg-purple-100 rounded-full dark:bg-purple-950 dark:text-purple-300">
            {{ __(':count résultat(s) trouvé(s)', ['count' => count($results)]) }}
        </span>
    </div>

    @if (empty($results))
        <div class="p-8 text-center space-y-3 bg-gray-50 dark:bg-gray-800/40 rounded-xl border border-dashed border-gray-300 dark:border-gray-700">
            <div class="w-12 h-12 mx-auto rounded-full bg-purple-100 dark:bg-purple-900/50 flex items-center justify-center text-purple-600 dark:text-purple-300">
                <x-filament::icon icon="heroicon-o-magnifying-glass" class="w-6 h-6" />
            </div>
            <h4 class="text-sm font-bold text-gray-900 dark:text-white">
                {{ __('Aucun résultat sémantique correspondant') }}
            </h4>
            <p class="text-xs text-gray-500 dark:text-gray-400 max-w-md mx-auto">
                {{ __('Essayez de reformuler votre idée ou d\'indexer vos liens existants via la commande Artisan `php artisan links:generate-embeddings`.') }}
            </p>
        </div>
    @else
        <div class="space-y-3 max-h-[60vh] overflow-y-auto pr-1">
            @foreach ($results as $link)
                @php
                    $score = $link->similarity_percentage ?? (int) round(($link->similarity_score ?? 0) * 100);
                    $badgeColor = $score >= 60 ? 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800' : ($score >= 35 ? 'bg-purple-100 text-purple-800 border-purple-300 dark:bg-purple-950/60 dark:text-purple-300 dark:border-purple-800' : 'bg-gray-100 text-gray-700 border-gray-300 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700');
                @endphp

                <div class="p-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl hover:border-purple-400 dark:hover:border-purple-600 transition-all shadow-xs space-y-2">
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-1 flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                @if ($link->favicon_url)
                                    <img src="{{ $link->favicon_url }}" class="w-4 h-4 rounded-xs shrink-0 object-contain" alt="" />
                                @endif
                                <h4 class="text-sm font-bold text-gray-900 dark:text-white truncate">
                                    {{ $link->title ?: $link->url }}
                                </h4>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                {{ $link->url }}
                            </p>
                        </div>

                        {{-- Badge de score de similarité --}}
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold rounded-full border {{ $badgeColor }} shrink-0">
                            <span>🎯</span>
                            <span>{{ $score }}%</span>
                        </span>
                    </div>

                    @if ($link->ai_summary || $link->description)
                        <p class="text-xs text-gray-600 dark:text-gray-300 line-clamp-2 bg-gray-50 dark:bg-gray-800/60 p-2 rounded-lg border border-gray-100 dark:border-gray-800">
                            {{ Str::limit(strip_tags($link->ai_summary ?: $link->description), 180) }}
                        </p>
                    @endif

                    <div class="flex items-center justify-between pt-1 text-[11px] text-gray-500">
                        <div class="flex items-center gap-2">
                            @if ($link->folder)
                                <span class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400">
                                    <x-filament::icon icon="heroicon-m-folder" class="w-3.5 h-3.5 text-primary-500" />
                                    <span>{{ $link->folder->name }}</span>
                                </span>
                            @endif
                            @if ($link->category)
                                <span class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400">
                                    <x-filament::icon icon="heroicon-m-tag" class="w-3.5 h-3.5 text-amber-500" />
                                    <span>{{ $link->category->name }}</span>
                                </span>
                            @endif
                        </div>

                        <div class="flex items-center gap-2">
                            <a 
                                href="{{ route('filament.app.resources.links.view', ['tenant' => $link->team->slug ?? Filament\Facades\Filament::getTenant()->slug, 'record' => $link->id]) }}" 
                                class="px-2.5 py-1 text-xs font-semibold text-gray-700 dark:text-gray-200 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 rounded-md transition-colors"
                            >
                                {{ __('Voir la fiche') }}
                            </a>
                            <a 
                                href="{{ route('links.visit', $link) }}" 
                                target="_blank" 
                                class="px-2.5 py-1 text-xs font-semibold text-white bg-primary-600 hover:bg-primary-500 rounded-md transition-colors flex items-center gap-1"
                            >
                                <span>{{ __('Ouvrir') }}</span>
                                <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="w-3.5 h-3.5" />
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
