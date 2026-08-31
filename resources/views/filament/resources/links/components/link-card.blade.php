@php
    /** @var \App\Models\Link $record */
    $record = $getRecord();
    if (! $record) {
        return;
    }
    $host = parse_url($record->url, PHP_URL_HOST) ?? $record->url;
    $cleanHost = preg_replace('/^www\./i', '', $host);
    $thumbnail = $record->content_type === \App\Enums\ContentType::Youtube 
        ? $record->getYoutubeThumbnailUrl() 
        : ($record->thumbnail_url ?: null);
    $viewUrl = \App\Filament\Resources\Links\LinkResource::getUrl('view', ['record' => $record->id]);
    $editUrl = \App\Filament\Resources\Links\LinkResource::getUrl('edit', ['record' => $record->id]);
    $isYoutube = $record->content_type === \App\Enums\ContentType::Youtube;
@endphp

<div class="group relative flex flex-col h-full bg-white dark:bg-gray-900 border border-gray-200/80 dark:border-gray-800 rounded-2xl overflow-hidden shadow-xs hover:shadow-xl hover:border-primary-500/40 dark:hover:border-primary-500/40 transition-all duration-300 transform hover:-translate-y-1">
    
    {{-- 1. Thumbnail / Media Header --}}
    <div class="relative w-full aspect-video bg-gray-100 dark:bg-gray-800/80 overflow-hidden border-b border-gray-100 dark:border-gray-800/80">
        @if ($thumbnail)
            <img 
                src="{{ $thumbnail }}" 
                alt="{{ $record->title }}" 
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 ease-out"
                loading="lazy"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
            />
            <div class="hidden absolute inset-0 bg-gradient-to-br from-primary-500/10 via-gray-100 to-purple-500/10 dark:from-primary-950/40 dark:via-gray-900 dark:to-purple-950/40 items-center justify-center">
                @if ($record->favicon_url)
                    <img src="{{ $record->favicon_url }}" alt="favicon" class="w-12 h-12 rounded-xl shadow-md" />
                @else
                    <x-filament::icon icon="heroicon-o-link" class="w-10 h-10 text-gray-400" />
                @endif
            </div>
        @else
            <div class="w-full h-full bg-gradient-to-br from-primary-500/10 via-gray-50 to-indigo-500/10 dark:from-primary-950/30 dark:via-gray-900 dark:to-indigo-950/30 flex items-center justify-center">
                @if ($record->favicon_url)
                    <img src="{{ $record->favicon_url }}" alt="favicon" class="w-12 h-12 rounded-xl shadow-md p-1 bg-white dark:bg-gray-800 border border-gray-200/60 dark:border-gray-700/60" />
                @else
                    <div class="w-12 h-12 rounded-xl bg-primary-50 dark:bg-primary-950/50 flex items-center justify-center text-primary-600 dark:text-primary-400 font-bold text-xl uppercase shadow-xs">
                        {{ substr($cleanHost, 0, 2) }}
                    </div>
                @endif
            </div>
        @endif

        {{-- Overlay YouTube Play Button --}}
        @if ($isYoutube)
            <a 
                href="{{ $viewUrl }}" 
                class="absolute inset-0 flex items-center justify-center bg-black/20 group-hover:bg-black/40 transition-colors"
                title="Regarder la vidéo"
            >
                <div class="flex items-center justify-center w-12 h-12 rounded-full bg-red-600/90 text-white shadow-lg group-hover:scale-110 transition-transform">
                    <x-filament::icon icon="heroicon-s-play" class="w-6 h-6 ml-0.5" />
                </div>
            </a>
        @else
            <a 
                href="{{ $viewUrl }}" 
                class="absolute inset-0"
                title="{{ $record->title }}"
            ></a>
        @endif

        {{-- Badges flottants sur l'image --}}
        <div class="absolute top-2.5 left-2.5 flex items-center gap-1.5 pointer-events-none">
            @if ($record->content_type)
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold backdrop-blur-md bg-black/65 text-white border border-white/10 shadow-xs">
                    @if ($isYoutube)
                        <x-filament::icon icon="heroicon-s-video-camera" class="w-3 h-3 text-red-400" />
                    @elseif ($record->content_type === \App\Enums\ContentType::Article)
                        <x-filament::icon icon="heroicon-m-document-text" class="w-3 h-3 text-blue-400" />
                    @else
                        <x-filament::icon icon="heroicon-m-sparkles" class="w-3 h-3 text-amber-400" />
                    @endif
                    <span>{{ $record->content_type->label() }}</span>
                </span>
            @endif
        </div>

        {{-- Bouton Favori en haut à droite --}}
        <div class="absolute top-2.5 right-2.5 z-10">
            @if ($record->is_favorite)
                <span class="flex items-center justify-center w-7 h-7 rounded-full backdrop-blur-md bg-amber-500/90 text-white shadow-xs" title="Favori">
                    <x-filament::icon icon="heroicon-s-star" class="w-4 h-4" />
                </span>
            @endif
        </div>
    </div>

    {{-- 2. Card Content --}}
    <div class="flex flex-col flex-1 p-4 sm:p-5 space-y-3">
        
        {{-- Domain Header & Folder --}}
        <div class="flex items-center justify-between gap-2 text-xs">
            <div class="flex items-center gap-1.5 min-w-0 text-gray-500 dark:text-gray-400">
                @if ($record->favicon_url)
                    <img src="{{ $record->favicon_url }}" alt="" class="w-3.5 h-3.5 rounded shrink-0" onerror="this.style.display='none'" />
                @endif
                <span class="font-medium truncate hover:text-gray-900 dark:hover:text-gray-200 transition">
                    {{ $cleanHost }}
                </span>
            </div>

            @if ($record->folder)
                <a 
                    href="{{ \App\Filament\Resources\Folders\FolderResource::getUrl('view', ['record' => $record->folder->id]) }}" 
                    class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-medium rounded-md bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300 hover:bg-blue-100 transition truncate max-w-[130px]"
                    title="Dossier : {{ $record->folder->name }}"
                >
                    <x-filament::icon icon="heroicon-m-folder" class="w-3 h-3 shrink-0" />
                    <span class="truncate">{{ $record->folder->name }}</span>
                </a>
            @endif
        </div>

        {{-- Titre --}}
        <h3 class="font-bold text-sm sm:text-base leading-snug text-gray-900 dark:text-white line-clamp-2 hover:text-primary-600 dark:hover:text-primary-400 transition">
            <a href="{{ $viewUrl }}">
                {{ $record->title ?: $record->url }}
            </a>
        </h3>

        {{-- Description --}}
        @if ($record->description)
            <p class="text-xs leading-relaxed text-gray-500 dark:text-gray-400 line-clamp-2">
                {{ $record->description }}
            </p>
        @endif

        {{-- Tags & Category --}}
        <div class="flex flex-wrap items-center gap-1.5 pt-1">
            @if ($record->category)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold rounded-md bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200/50 dark:border-emerald-800/40">
                    <x-filament::icon icon="heroicon-m-tag" class="w-2.5 h-2.5" />
                    <span>{{ $record->category->name }}</span>
                </span>
            @endif

            @if ($record->tags && count(is_array($record->tags) ? $record->tags : explode(',', $record->tags)) > 0)
                @php
                    $tagsList = is_array($record->tags) ? $record->tags : array_filter(array_map('trim', explode(',', $record->tags)));
                    $tagsPreview = array_slice($tagsList, 0, 2);
                    $moreCount = count($tagsList) - 2;
                @endphp
                @foreach ($tagsPreview as $tag)
                    <span class="inline-flex items-center text-[10px] text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">
                        #{{ is_object($tag) ? $tag->name : $tag }}
                    </span>
                @endforeach
                @if ($moreCount > 0)
                    <span class="text-[10px] text-gray-400 font-medium">+{{ $moreCount }}</span>
                @endif
            @endif
        </div>

        {{-- Spacer to push footer to bottom --}}
        <div class="flex-1"></div>

        {{-- 3. Card Footer --}}
        <div class="flex items-center justify-between pt-3 border-t border-gray-100 dark:border-gray-800/80 text-xs">
            <span class="text-gray-400 dark:text-gray-500 text-[11px]">
                {{ $record->created_at?->diffForHumans() ?? '—' }}
            </span>

            <div class="flex items-center gap-1.5">
                <a 
                    href="{{ $record->url }}" 
                    target="_blank" 
                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg bg-primary-50 text-primary-700 hover:bg-primary-100 dark:bg-primary-950/40 dark:text-primary-300 dark:hover:bg-primary-900/50 transition duration-150"
                    title="Ouvrir le lien dans un nouvel onglet"
                >
                    <span>Ouvrir</span>
                    <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="w-3.5 h-3.5" />
                </a>

                <a 
                    href="{{ $viewUrl }}" 
                    class="p-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                    title="Voir les détails"
                >
                    <x-filament::icon icon="heroicon-m-eye" class="w-4 h-4" />
                </a>

                <a 
                    href="{{ $editUrl }}" 
                    class="p-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                    title="Modifier"
                >
                    <x-filament::icon icon="heroicon-m-pencil-square" class="w-4 h-4" />
                </a>
            </div>
        </div>
    </div>
</div>
