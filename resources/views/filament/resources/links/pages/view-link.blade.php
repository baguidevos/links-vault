<x-filament-panels::page>
    <div 
        x-data="{
            copied: false,
            copyUrl(url) {
                navigator.clipboard.writeText(url);
                this.copied = true;
                setTimeout(() => this.copied = false, 2500);
            }
        }"
        x-on:keydown.window.e.prevent="$wire.mountAction('edit')"
        class="space-y-6"
    >
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- Colonne Principale (Contenu du Lien) --}}
            <div class="space-y-6 lg:col-span-2">
                {{-- Carte Principale --}}
                <div class="overflow-hidden bg-white border border-gray-200 shadow-xs rounded-2xl dark:bg-gray-900 dark:border-gray-800">
                    {{-- Media Header : YouTube Embed ou Image Thumbnail --}}
                    @if ($record->getYoutubeEmbedUrl())
                        <div class="relative w-full overflow-hidden bg-black aspect-video">
                            <iframe 
                                src="{{ $record->getYoutubeEmbedUrl() }}" 
                                title="{{ $record->title }}"
                                class="w-full h-full border-0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                                allowfullscreen
                            ></iframe>
                        </div>
                    @elseif ($record->thumbnail_url)
                        <div class="relative w-full max-h-80 overflow-hidden bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-800">
                            <img 
                                src="{{ $record->thumbnail_url }}" 
                                alt="{{ $record->title }}" 
                                class="object-cover w-full h-full max-h-80"
                                onerror="this.style.display='none'"
                            />
                        </div>
                    @endif

                    <div class="p-6 sm:p-8 space-y-6">
                        {{-- Badges de Catégorie & Type --}}
                        <div class="flex flex-wrap items-center gap-2.5">
                            @if ($record->content_type)
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-full bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200/60 dark:border-amber-800/40">
                                    <x-filament::icon icon="heroicon-m-sparkles" class="w-3.5 h-3.5" />
                                    <span>{{ $record->content_type->label() ?? $record->content_type->value }}</span>
                                </span>
                            @endif

                            @if ($record->category)
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/40">
                                    <x-filament::icon icon="heroicon-m-folder" class="w-3.5 h-3.5" />
                                    <span>{{ $record->category->name }}</span>
                                </span>
                            @endif

                            @if ($record->is_favorite)
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-full bg-yellow-50 text-yellow-700 dark:bg-yellow-950/40 dark:text-yellow-300 border border-yellow-200/60 dark:border-yellow-800/40">
                                    <x-filament::icon icon="heroicon-s-star" class="w-3.5 h-3.5 text-yellow-500" />
                                    <span>Favori</span>
                                </span>
                            @endif

                            @if ($record->is_archived)
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                    <x-filament::icon icon="heroicon-m-archive-box" class="w-3.5 h-3.5" />
                                    <span>Archivé</span>
                                </span>
                            @endif
                        </div>

                        {{-- Titre du Lien --}}
                        <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl dark:text-white">
                            {{ $record->title ?: $record->url }}
                        </h1>

                        {{-- Boîte URL Interactive --}}
                        <div class="flex items-center gap-3 p-3.5 bg-gray-50 dark:bg-gray-800/60 rounded-xl border border-gray-200/70 dark:border-gray-700/60">
                            @if ($record->favicon_url)
                                <img src="{{ $record->favicon_url }}" alt="favicon" class="w-5 h-5 rounded shrink-0" onerror="this.style.display='none'" />
                            @else
                                <x-filament::icon icon="heroicon-o-link" class="w-5 h-5 text-gray-400 shrink-0" />
                            @endif

                            <a 
                                href="{{ $record->url }}" 
                                target="_blank" 
                                wire:click="recordVisit"
                                class="flex-1 text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline truncate"
                            >
                                {{ $record->url }}
                            </a>

                            <div class="flex items-center gap-1.5 shrink-0">
                                <button 
                                    type="button" 
                                    x-on:click="copyUrl('{{ addslashes($record->url) }}')"
                                    class="p-2 text-gray-500 rounded-lg hover:text-gray-900 hover:bg-gray-200/60 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700 transition"
                                    title="Copier le lien"
                                >
                                    <template x-if="!copied">
                                        <x-filament::icon icon="heroicon-o-clipboard-document" class="w-4 h-4" />
                                    </template>
                                    <template x-if="copied">
                                        <x-filament::icon icon="heroicon-o-check" class="w-4 h-4 text-emerald-500" />
                                    </template>
                                </button>

                                <a 
                                    href="{{ $record->url }}" 
                                    target="_blank" 
                                    wire:click="recordVisit"
                                    class="p-2 text-gray-500 rounded-lg hover:text-gray-900 hover:bg-gray-200/60 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700 transition"
                                    title="Ouvrir dans un nouvel onglet"
                                >
                                    <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" class="w-4 h-4" />
                                </a>
                            </div>
                        </div>

                        {{-- Description --}}
                        @if ($record->description)
                            <div class="space-y-2">
                                <h3 class="text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                                    Description
                                </h3>
                                <div class="text-sm leading-relaxed text-gray-700 dark:text-gray-300 prose dark:prose-invert max-w-none">
                                    {!! nl2br(e($record->description)) !!}
                                </div>
                            </div>
                        @endif

                        {{-- Grille de Métadonnées / Statistiques --}}
                        <div class="grid grid-cols-2 gap-4 pt-4 border-t border-gray-100 sm:grid-cols-4 dark:border-gray-800">
                            <div class="space-y-1">
                                <span class="text-xs text-gray-500 dark:text-gray-400">Ajouté le</span>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $record->created_at?->translatedFormat('d M Y') ?? '—' }}
                                </p>
                            </div>
                            <div class="space-y-1">
                                <span class="text-xs text-gray-500 dark:text-gray-400">Dernière visite</span>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $record->last_visited_at ? $record->last_visited_at->diffForHumans() : 'Jamais' }}
                                </p>
                            </div>
                            <div class="space-y-1">
                                <span class="text-xs text-gray-500 dark:text-gray-400">Nombre de vues</span>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $record->visit_count }} fois
                                </p>
                            </div>
                            <div class="space-y-1">
                                <span class="text-xs text-gray-500 dark:text-gray-400">Partages</span>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $record->shares()->count() }}
                                </p>
                            </div>
                        </div>

                        {{-- Objectif Associé --}}
                        @if ($record->objective)
                            <div class="space-y-2 pt-4 border-t border-gray-100 dark:border-gray-800">
                                <h3 class="text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                                    Objectif
                                </h3>
                                <div class="flex items-start gap-3.5 p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-900 dark:text-amber-200">
                                    <div class="p-2 text-white bg-amber-600 rounded-lg shrink-0">
                                        <x-filament::icon icon="heroicon-m-flag" class="w-5 h-5" />
                                    </div>
                                    <div class="space-y-0.5">
                                        <p class="text-sm font-semibold">{{ $record->objective }}</p>
                                        <p class="text-xs text-amber-700 dark:text-amber-300/80">Déterminé pour ce lien</p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Tags --}}
                        @if ($record->tags && count(is_array($record->tags) ? $record->tags : explode(',', $record->tags)) > 0)
                            @php
                                $tagsList = is_array($record->tags) ? $record->tags : array_filter(array_map('trim', explode(',', $record->tags)));
                            @endphp
                            @if (count($tagsList) > 0)
                                <div class="space-y-2 pt-4 border-t border-gray-100 dark:border-gray-800">
                                    <h3 class="text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                                        Tags
                                    </h3>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($tagsList as $tag)
                                            <span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200 rounded-lg border border-gray-200 dark:border-gray-700">
                                                <x-filament::icon icon="heroicon-m-hashtag" class="w-3 h-3 text-gray-400" />
                                                <span>{{ is_object($tag) ? $tag->name : $tag }}</span>
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>

            {{-- Colonne Latérale (Actions, IA, Similaires) --}}
            <div class="space-y-6">
                {{-- Carte Actions Rapides --}}
                <div class="p-6 bg-white border border-gray-200 shadow-xs rounded-2xl dark:bg-gray-900 dark:border-gray-800 space-y-4">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                        Actions Rapides
                    </h2>

                    <div class="flex flex-col gap-2.5">
                        <a 
                            href="{{ $record->url }}" 
                            target="_blank" 
                            wire:click="recordVisit"
                            class="inline-flex items-center justify-center gap-2 w-full px-4 py-2.5 text-sm font-semibold text-white bg-primary-600 hover:bg-primary-500 rounded-xl transition duration-150 shadow-xs"
                        >
                            <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" class="w-4 h-4" />
                            <span>Ouvrir le lien</span>
                        </a>

                        <button 
                            type="button" 
                            wire:click="mountAction('edit')"
                            class="inline-flex items-center justify-center gap-2 w-full px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-50 hover:bg-gray-100 dark:text-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700 rounded-xl transition"
                        >
                            <x-filament::icon icon="heroicon-o-pencil-square" class="w-4 h-4" />
                            <span>Modifier</span>
                        </button>

                        <button 
                            type="button" 
                            wire:click="mountAction('share_link')"
                            class="inline-flex items-center justify-center gap-2 w-full px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-50 hover:bg-gray-100 dark:text-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700 rounded-xl transition"
                        >
                            <x-filament::icon icon="heroicon-o-share" class="w-4 h-4" />
                            <span>Partager</span>
                        </button>

                        <button 
                            type="button" 
                            wire:click="toggleFavorite"
                            class="inline-flex items-center justify-center gap-2 w-full px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-50 hover:bg-gray-100 dark:text-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700 rounded-xl transition"
                        >
                            <x-filament::icon 
                                :icon="$record->is_favorite ? 'heroicon-s-star' : 'heroicon-o-star'" 
                                class="w-4 h-4 {{ $record->is_favorite ? 'text-yellow-500' : '' }}" 
                            />
                            <span>{{ $record->is_favorite ? 'Retirer des favoris' : 'Ajouter aux favoris' }}</span>
                        </button>

                        <button 
                            type="button" 
                            wire:click="mountAction('delete')"
                            class="inline-flex items-center justify-center gap-2 w-full px-4 py-2 text-sm font-semibold text-red-600 bg-red-50 hover:bg-red-100 dark:text-red-400 dark:bg-red-950/30 dark:hover:bg-red-900/40 border border-red-200/60 dark:border-red-900/50 rounded-xl transition"
                        >
                            <x-filament::icon icon="heroicon-o-trash" class="w-4 h-4" />
                            <span>Supprimer</span>
                        </button>
                    </div>
                </div>

                {{-- Carte Résumé IA --}}
                @if ($record->ai_summary)
                    <div class="p-6 bg-white border border-gray-200 shadow-xs rounded-2xl dark:bg-gray-900 dark:border-gray-800 space-y-3">
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                <x-filament::icon icon="heroicon-m-sparkles" class="w-4 h-4 text-primary-500" />
                                <span>Résumé IA</span>
                            </h2>
                            <span class="text-[11px] font-medium px-2 py-0.5 rounded-md bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-300">
                                Automatique
                            </span>
                        </div>

                        <div class="text-sm leading-relaxed text-gray-600 dark:text-gray-300 prose prose-sm dark:prose-invert max-w-none">
                            {!! nl2br(e($record->ai_summary)) !!}
                        </div>
                    </div>
                @endif

                {{-- Carte Ressources Similaires --}}
                @php
                    $similarLinks = $this->getSimilarLinks();
                @endphp
                @if ($similarLinks->isNotEmpty())
                    <div class="p-6 bg-white border border-gray-200 shadow-xs rounded-2xl dark:bg-gray-900 dark:border-gray-800 space-y-4">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <x-filament::icon icon="heroicon-m-rectangle-stack" class="w-4 h-4 text-gray-400" />
                            <span>Ressources Similaires</span>
                        </h2>

                        <div class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($similarLinks as $similar)
                                <a 
                                    href="{{ \App\Filament\Resources\Links\LinkResource::getUrl('view', ['record' => $similar->id]) }}" 
                                    class="flex items-center gap-3 py-3 group first:pt-0 last:pb-0 hover:bg-gray-50 dark:hover:bg-gray-800/40 -mx-2 px-2 rounded-xl transition"
                                >
                                    <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 shrink-0 group-hover:bg-primary-50 group-hover:text-primary-600 dark:group-hover:bg-primary-950/40 dark:group-hover:text-primary-400 transition">
                                        @if ($similar->content_type?->value === 'video' || str_contains($similar->url, 'youtube.com') || str_contains($similar->url, 'youtu.be'))
                                            <x-filament::icon icon="heroicon-m-play" class="w-4 h-4" />
                                        @elseif ($similar->content_type?->value === 'article')
                                            <x-filament::icon icon="heroicon-m-document-text" class="w-4 h-4" />
                                        @else
                                            <x-filament::icon icon="heroicon-m-link" class="w-4 h-4" />
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold text-gray-900 dark:text-white truncate group-hover:text-primary-600 dark:group-hover:text-primary-400 transition">
                                            {{ $similar->title ?: $similar->url }}
                                        </p>
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                                            {{ parse_url($similar->url, PHP_URL_HOST) ?? $similar->url }}
                                        </p>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Barre d'astuces raccourcis clavier --}}
        <div class="hidden sm:flex items-center justify-center gap-6 py-2.5 text-xs text-gray-400 dark:text-gray-500">
            <span class="inline-flex items-center gap-1.5">
                <kbd class="px-2 py-0.5 font-mono text-[11px] font-semibold text-gray-700 bg-gray-100 border border-gray-300 rounded dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700">E</kbd>
                <span>Modifier</span>
            </span>
            <span class="inline-flex items-center gap-1.5">
                <kbd class="px-2 py-0.5 font-mono text-[11px] font-semibold text-gray-700 bg-gray-100 border border-gray-300 rounded dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700">Esc</kbd>
                <span>Fermer / Retour</span>
            </span>
        </div>
    </div>
</x-filament-panels::page>
