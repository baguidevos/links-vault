<x-filament-panels::page>
    @php
        $stats = $this->getFolderStats();
        $links = $this->getFolderLinks();
        $relatedFolders = $this->getRelatedFolders();
        $folderColor = $record->color ?: '#0099FF';
    @endphp

    <div 
        x-data="{
            copied: false,
            copiedId: null,
            copyText(text, id = null) {
                navigator.clipboard.writeText(text);
                if (id) {
                    this.copiedId = id;
                    setTimeout(() => this.copiedId = null, 2000);
                } else {
                    this.copied = true;
                    setTimeout(() => this.copied = false, 2000);
                }
            }
        }"
        class="space-y-8"
    >
        {{-- Hero Header du Dossier --}}
        <div class="relative overflow-hidden bg-white border border-gray-200 shadow-xs rounded-2xl dark:bg-gray-900 dark:border-gray-800">
            {{-- Bandeau supérieur avec couleur d'accent --}}
            <div class="h-3 w-full" style="background: linear-gradient(90deg, {{ $folderColor }}, {{ $folderColor }}88);"></div>

            <div class="p-6 sm:p-8 space-y-6">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex items-start gap-4">
                        {{-- Icône du Dossier --}}
                        <div 
                            class="flex items-center justify-center w-14 h-14 rounded-2xl shadow-sm shrink-0 border border-gray-100 dark:border-gray-800"
                            style="background-color: {{ $folderColor }}18; color: {{ $folderColor }};"
                        >
                            @if ($record->icon)
                                <x-filament::icon :icon="$record->icon" class="w-7 h-7" />
                            @else
                                <x-filament::icon icon="heroicon-o-folder" class="w-7 h-7" />
                            @endif
                        </div>

                        <div class="space-y-1.5 min-w-0">
                            {{-- Badges de statut & visibilité --}}
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($record->category)
                                    <a 
                                        href="{{ \App\Filament\Resources\Categories\CategoryResource::getUrl('view', ['record' => $record->category->id]) }}"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-full bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 transition"
                                    >
                                        <x-filament::icon icon="heroicon-m-tag" class="w-3.5 h-3.5 text-gray-500" />
                                        <span>{{ $record->category->name }}</span>
                                    </a>
                                @endif

                                @php
                                    $visEnum = $record->visibility;
                                    $visLabel = method_exists($visEnum, 'getLabel') ? $visEnum->getLabel() : (string) $visEnum;
                                    $visColor = method_exists($visEnum, 'getColor') ? $visEnum->getColor() : 'gray';
                                    $visIcon = method_exists($visEnum, 'getIcon') ? $visEnum->getIcon() : 'heroicon-m-lock-closed';
                                @endphp

                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-full bg-{{ $visColor }}-50 text-{{ $visColor }}-700 dark:bg-{{ $visColor }}-950/40 dark:text-{{ $visColor }}-300 border border-{{ $visColor }}-200/60 dark:border-{{ $visColor }}-800/40">
                                    <x-filament::icon :icon="$visIcon" class="w-3.5 h-3.5" />
                                    <span>{{ $visLabel }}</span>
                                </span>

                                @if ($record->members->isNotEmpty() && in_array($record->visibility?->value ?? (string)$record->visibility, ['restricted']))
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-300">
                                        <x-filament::icon icon="heroicon-m-users" class="w-3 h-3" />
                                        <span>{{ $record->members->count() }} assigné(s)</span>
                                    </span>
                                @endif
                            </div>

                            <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl dark:text-white truncate">
                                {{ $record->name }}
                            </h1>

                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Créé par <span class="font-medium text-gray-700 dark:text-gray-300">{{ $record->user?->name ?? 'Utilisateur' }}</span>
                                &bull; {{ $record->created_at?->translatedFormat('d F Y') }}
                            </p>
                        </div>
                    </div>

                    {{-- Actions rapides du Dossier --}}
                    <div class="flex items-center gap-2 shrink-0">
                        <button
                            type="button"
                            wire:click="mountAction('add_link')"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-primary-600 hover:bg-primary-500 rounded-xl transition duration-150 shadow-xs cursor-pointer"
                        >
                            <x-filament::icon icon="heroicon-o-plus" class="w-4 h-4" />
                            <span>Ajouter un lien</span>
                        </button>

                        @if ($record->canChangeVisibility(auth()->user()))
                            <button
                                type="button"
                                wire:click="mountAction('change_visibility')"
                                class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 dark:text-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 rounded-xl transition cursor-pointer"
                                title="Modifier la visibilité"
                            >
                                <x-filament::icon icon="heroicon-o-lock-closed" class="w-4 h-4 text-primary-500" />
                                <span class="hidden sm:inline">Visibilité</span>
                            </button>
                        @endif

                        <button
                            type="button"
                            wire:click="mountAction('edit')"
                            class="p-2 text-gray-600 bg-gray-100 hover:bg-gray-200 dark:text-gray-300 dark:bg-gray-800 dark:hover:bg-gray-700 rounded-xl transition cursor-pointer"
                            title="Modifier le dossier"
                        >
                            <x-filament::icon icon="heroicon-o-pencil-square" class="w-5 h-5" />
                        </button>
                    </div>
                </div>

                {{-- Description du Dossier --}}
                @if ($record->description)
                    <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-800">
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed prose dark:prose-invert max-w-none">
                            {!! nl2br(e($record->description)) !!}
                        </p>
                    </div>
                @endif

                {{-- Grille de Métriques / Statistiques du Dossier --}}
                <div class="grid grid-cols-2 gap-4 pt-4 border-t border-gray-100 sm:grid-cols-4 dark:border-gray-800">
                    <div class="space-y-1 p-3 rounded-xl bg-gray-50/70 dark:bg-gray-800/40 border border-gray-100 dark:border-gray-800">
                        <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <x-filament::icon icon="heroicon-o-link" class="w-4 h-4 text-primary-500" />
                            <span>Total des liens</span>
                        </div>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">
                            {{ $stats['total_links'] }}
                        </p>
                    </div>

                    <div class="space-y-1 p-3 rounded-xl bg-gray-50/70 dark:bg-gray-800/40 border border-gray-100 dark:border-gray-800">
                        <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <x-filament::icon icon="heroicon-o-eye" class="w-4 h-4 text-blue-500" />
                            <span>Total des visites</span>
                        </div>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">
                            {{ $stats['total_visits'] }}
                        </p>
                    </div>

                    <div class="space-y-1 p-3 rounded-xl bg-gray-50/70 dark:bg-gray-800/40 border border-gray-100 dark:border-gray-800">
                        <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <x-filament::icon icon="heroicon-o-star" class="w-4 h-4 text-yellow-500" />
                            <span>Favoris</span>
                        </div>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">
                            {{ $stats['favorites_count'] }}
                        </p>
                    </div>

                    <div class="space-y-1 p-3 rounded-xl bg-gray-50/70 dark:bg-gray-800/40 border border-gray-100 dark:border-gray-800">
                        <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <x-filament::icon icon="heroicon-o-users" class="w-4 h-4 text-emerald-500" />
                            <span>Accès</span>
                        </div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white mt-1">
                            {{ $visLabel }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section Principale : Liens & Barre Latérale --}}
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
            {{-- Colonne Liens (2/3) --}}
            <div class="space-y-5 lg:col-span-2">
                {{-- Barre de recherche & Filtres de types --}}
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-white p-3.5 border border-gray-200 shadow-xs rounded-2xl dark:bg-gray-900 dark:border-gray-800">
                    {{-- Input de recherche dynamique --}}
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                            <x-filament::icon icon="heroicon-o-magnifying-glass" class="w-4 h-4" />
                        </div>
                        <input
                            type="text"
                            wire:model.live.debounce.250ms="searchQuery"
                            placeholder="Rechercher dans ce dossier (titre, URL, description)..."
                            class="w-full pl-9 pr-8 py-2 text-sm bg-gray-50 dark:bg-gray-800/80 border border-gray-200 dark:border-gray-700 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-transparent text-gray-900 dark:text-white placeholder-gray-400"
                        />
                        @if ($searchQuery)
                            <button
                                type="button"
                                wire:click="$set('searchQuery', '')"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
                            >
                                <x-filament::icon icon="heroicon-o-x-mark" class="w-4 h-4" />
                            </button>
                        @endif
                    </div>

                    {{-- Filtre par type de contenu --}}
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
                        <button
                            type="button"
                            wire:click="$set('selectedType', null)"
                            class="px-3 py-1.5 text-xs font-semibold rounded-lg transition whitespace-nowrap cursor-pointer {{ is_null($selectedType) ? 'bg-primary-600 text-white shadow-xs' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                        >
                            Tous ({{ $stats['total_links'] }})
                        </button>

                        @foreach ($stats['types_breakdown'] as $type => $count)
                            <button
                                type="button"
                                wire:click="$set('selectedType', '{{ $type }}')"
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition whitespace-nowrap cursor-pointer {{ $selectedType === $type ? 'bg-primary-600 text-white shadow-xs' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                            >
                                {{ ucfirst($type) }} ({{ $count }})
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Liste des Liens dans le Dossier --}}
                @if ($links->isEmpty())
                    <div class="flex flex-col items-center justify-center p-12 text-center bg-white border border-gray-200 border-dashed rounded-2xl dark:bg-gray-900 dark:border-gray-800 space-y-4">
                        <div class="flex items-center justify-center w-14 h-14 rounded-full bg-gray-50 dark:bg-gray-800 text-gray-400">
                            <x-filament::icon icon="heroicon-o-link" class="w-7 h-7" />
                        </div>
                        <div class="space-y-1">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                {{ $searchQuery || $selectedType ? 'Aucun lien correspondant' : 'Ce dossier est vide' }}
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 max-w-sm">
                                {{ $searchQuery || $selectedType ? 'Essayez de modifier vos termes de recherche ou de réinitialiser le filtre.' : 'Commencez à organiser vos ressources en ajoutant votre premier lien dans ce dossier.' }}
                            </p>
                        </div>

                        @if ($searchQuery || $selectedType)
                            <button
                                type="button"
                                wire:click="$set('searchQuery', ''); $set('selectedType', null)"
                                class="px-4 py-2 text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 rounded-xl transition cursor-pointer"
                            >
                                Réinitialiser les filtres
                            </button>
                        @else
                            <button
                                type="button"
                                wire:click="mountAction('add_link')"
                                class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-semibold text-white bg-primary-600 hover:bg-primary-500 rounded-xl transition shadow-xs cursor-pointer"
                            >
                                <x-filament::icon icon="heroicon-o-plus" class="w-4 h-4" />
                                <span>Ajouter un lien maintenant</span>
                            </button>
                        @endif
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-4">
                        @foreach ($links as $link)
                            <div class="group relative flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-4 bg-white border border-gray-200 hover:border-primary-400 dark:hover:border-primary-500/50 shadow-xs hover:shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-800 transition duration-150">
                                <div class="flex items-start gap-3.5 min-w-0 flex-1">
                                    {{-- Thumbnail / Favicon --}}
                                    <div class="relative w-12 h-12 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center overflow-hidden shrink-0 border border-gray-200/60 dark:border-gray-700/60">
                                        @if ($link->thumbnail_url && $link->thumbnail_url !== 'nom disponible')
                                            <img src="{{ $link->thumbnail_url }}" alt="thumb" class="w-full h-full object-cover" onerror="this.style.display='none'" />
                                        @elseif ($link->favicon_url)
                                            <img src="{{ $link->favicon_url }}" alt="favicon" class="w-6 h-6 rounded" onerror="this.style.display='none'" />
                                        @else
                                            <x-filament::icon icon="heroicon-o-globe-alt" class="w-6 h-6 text-gray-400" />
                                        @endif
                                    </div>

                                    {{-- Infos du Lien --}}
                                    <div class="space-y-1 min-w-0 flex-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <a
                                                href="{{ \App\Filament\Resources\Links\LinkResource::getUrl('view', ['record' => $link->id]) }}"
                                                class="text-sm font-bold text-gray-900 hover:text-primary-600 dark:text-white dark:hover:text-primary-400 truncate"
                                            >
                                                {{ $link->title ?: $link->url }}
                                            </a>

                                            @if ($link->is_favorite)
                                                <x-filament::icon icon="heroicon-s-star" class="w-4 h-4 text-yellow-500 shrink-0" />
                                            @endif

                                            @if ($link->content_type)
                                                <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-semibold rounded-md bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                                    {{ $link->content_type->label() ?? $link->content_type->value }}
                                                </span>
                                            @endif
                                        </div>

                                        {{-- URL & Domaine --}}
                                        <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                            <span class="truncate max-w-xs font-mono text-[11px]">{{ parse_url($link->url, PHP_URL_HOST) ?? $link->url }}</span>
                                            <span>&bull;</span>
                                            <span>{{ $link->visit_count }} vue(s)</span>
                                            <span>&bull;</span>
                                            <span>{{ $link->created_at?->diffForHumans() }}</span>
                                        </div>

                                        @if ($link->description)
                                            <p class="text-xs text-gray-600 dark:text-gray-400 line-clamp-1">
                                                {{ $link->description }}
                                            </p>
                                        @endif
                                    </div>
                                </div>

                                {{-- Boutons d'Action sur le Lien --}}
                                <div class="flex items-center gap-1 shrink-0 self-end sm:self-center">
                                    {{-- Copier le lien --}}
                                    <button
                                        type="button"
                                        x-on:click="copyText('{{ addslashes($link->url) }}', {{ $link->id }})"
                                        class="p-2 text-gray-400 hover:text-gray-700 dark:hover:text-white rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
                                        title="Copier le lien"
                                    >
                                        <template x-if="copiedId !== {{ $link->id }}">
                                            <x-filament::icon icon="heroicon-o-clipboard-document" class="w-4 h-4" />
                                        </template>
                                        <template x-if="copiedId === {{ $link->id }}">
                                            <x-filament::icon icon="heroicon-o-check" class="w-4 h-4 text-emerald-500" />
                                        </template>
                                    </button>

                                    {{-- Basculer Favori --}}
                                    <button
                                        type="button"
                                        wire:click="toggleLinkFavorite({{ $link->id }})"
                                        class="p-2 text-gray-400 hover:text-yellow-500 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
                                        title="{{ $link->is_favorite ? 'Retirer des favoris' : 'Ajouter aux favoris' }}"
                                    >
                                        <x-filament::icon
                                            :icon="$link->is_favorite ? 'heroicon-s-star' : 'heroicon-o-star'"
                                            class="w-4 h-4 {{ $link->is_favorite ? 'text-yellow-500' : '' }}"
                                        />
                                    </button>

                                    {{-- Détails --}}
                                    <a
                                        href="{{ \App\Filament\Resources\Links\LinkResource::getUrl('view', ['record' => $link->id]) }}"
                                        class="p-2 text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                                        title="Voir les détails"
                                    >
                                        <x-filament::icon icon="heroicon-o-eye" class="w-4 h-4" />
                                    </a>

                                    {{-- Ouvrir dans un nouvel onglet --}}
                                    <a
                                        href="{{ $link->url }}"
                                        target="_blank"
                                        wire:click="recordLinkVisit({{ $link->id }})"
                                        class="p-2 text-primary-600 hover:text-primary-700 bg-primary-50 dark:bg-primary-950/40 dark:text-primary-400 rounded-lg hover:bg-primary-100 dark:hover:bg-primary-900/50 transition"
                                        title="Ouvrir le lien"
                                    >
                                        <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" class="w-4 h-4" />
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Colonne Latérale (Accès, Catégorie, Dossiers Liés) --}}
            <div class="space-y-6">
                {{-- Carte Contrôle d'Accès & Membres --}}
                <div class="p-6 bg-white border border-gray-200 shadow-xs rounded-2xl dark:bg-gray-900 dark:border-gray-800 space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <x-filament::icon icon="heroicon-m-shield-check" class="w-4 h-4 text-primary-500" />
                            <span>Accès & Permissions</span>
                        </h2>
                        <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                            {{ $visLabel }}
                        </span>
                    </div>

                    <div class="space-y-3 divide-y divide-gray-100 dark:divide-gray-800 text-xs">
                        {{-- Propriétaire --}}
                        <div class="flex items-center justify-between pt-2 first:pt-0">
                            <div class="flex items-center gap-2">
                                <div class="flex items-center justify-center w-7 h-7 rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-300 font-bold text-xs">
                                    {{ strtoupper(substr($record->user?->name ?? 'P', 0, 1)) }}
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $record->user?->name ?? 'Propriétaire' }}</p>
                                    <p class="text-[10px] text-gray-500">Créateur du dossier</p>
                                </div>
                            </div>
                            <span class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 text-[10px] font-semibold">
                                Propriétaire
                            </span>
                        </div>

                        {{-- Membres restreints assignés --}}
                        @if ($record->members->isNotEmpty())
                            @foreach ($record->members as $member)
                                <div class="flex items-center justify-between pt-2">
                                    <div class="flex items-center gap-2">
                                        <div class="flex items-center justify-center w-7 h-7 rounded-full bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 font-bold text-xs">
                                            {{ strtoupper(substr($member->name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900 dark:text-white">{{ $member->name }}</p>
                                            <p class="text-[10px] text-gray-500">{{ $member->email }}</p>
                                        </div>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-md {{ ($member->pivot->role ?? '') === 'editor' ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }} text-[10px] font-semibold">
                                        {{ ($member->pivot->role ?? '') === 'editor' ? '✏️ Éditeur' : '👁️ Lecteur' }}
                                    </span>
                                </div>
                            @endforeach
                        @elseif (in_array($record->visibility?->value ?? (string)$record->visibility, ['team']))
                            <div class="pt-2 text-gray-500 dark:text-gray-400 leading-relaxed">
                                <p>Tous les membres de l'équipe <span class="font-semibold text-gray-900 dark:text-white">{{ $record->team?->name }}</span> ont accès en lecture et ajout à ce dossier.</p>
                            </div>
                        @else
                            <div class="pt-2 text-gray-500 dark:text-gray-400 leading-relaxed">
                                <p>Dossier privé. Aucun autre membre n'a accès à ce dossier.</p>
                            </div>
                        @endif
                    </div>

                    <div class="pt-2">
                        <button
                            type="button"
                            wire:click="mountAction('edit')"
                            class="w-full py-2 text-xs font-semibold text-gray-700 bg-gray-50 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700 rounded-xl transition text-center cursor-pointer"
                        >
                            Gérer les accès
                        </button>
                    </div>
                </div>

                {{-- Autres dossiers dans la même catégorie / équipe --}}
                @if ($relatedFolders->isNotEmpty())
                    <div class="p-6 bg-white border border-gray-200 shadow-xs rounded-2xl dark:bg-gray-900 dark:border-gray-800 space-y-4">
                        <h2 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <x-filament::icon icon="heroicon-m-folder" class="w-4 h-4 text-gray-400" />
                            <span>Autres dossiers</span>
                        </h2>

                        <div class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($relatedFolders as $other)
                                <a
                                    href="{{ \App\Filament\Resources\Folders\FolderResource::getUrl('view', ['record' => $other->id]) }}"
                                    class="flex items-center justify-between py-2.5 group first:pt-0 last:pb-0 hover:bg-gray-50 dark:hover:bg-gray-800/40 -mx-2 px-2 rounded-xl transition"
                                >
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <div 
                                            class="w-3 h-3 rounded-full shrink-0"
                                            style="background-color: {{ $other->color ?: '#0099FF' }};"
                                        ></div>
                                        <span class="text-xs font-semibold text-gray-900 dark:text-white truncate group-hover:text-primary-600 dark:group-hover:text-primary-400 transition">
                                            {{ $other->name }}
                                        </span>
                                    </div>
                                    <span class="text-[11px] font-medium text-gray-400 shrink-0">
                                        {{ $other->links_count }} lien(s)
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
