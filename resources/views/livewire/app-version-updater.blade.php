<div class="w-full">
    <!-- Sidebar Ouverte (Expanded) -->
    <div 
        x-show="$store.sidebar.isOpen"
        class="p-3 mx-2 my-1 rounded-xl border border-gray-200/70 dark:border-white/10 bg-gray-50/80 dark:bg-gray-900/60 shadow-xs space-y-2 transition-all duration-150"
    >
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0">
                <span class="relative flex h-2 w-2">
                    @if ($updateDownloaded)
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    @elseif ($updateAvailable)
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-400"></span>
                    @else
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-400"></span>
                    @endif
                </span>
                
                <span class="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate">
                    LinksVault
                </span>
            </div>

            <span class="px-2 py-0.5 text-[11px] font-mono font-medium rounded-full bg-indigo-500/10 text-indigo-500 dark:text-indigo-400 border border-indigo-500/20">
                v{{ $currentVersion }}
            </span>
        </div>

        @if ($updateDownloaded)
            <div class="pt-1">
                <button
                    type="button"
                    wire:click="installUpdate"
                    class="w-full py-1.5 px-2.5 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-500 active:scale-98 rounded-lg flex items-center justify-center gap-1.5 transition shadow-xs cursor-pointer"
                >
                    <x-tabler-refresh class="w-3.5 h-3.5" />
                    <span>Redémarrer & Installer</span>
                </button>
            </div>
        @elseif ($updateAvailable)
            <div class="pt-1">
                <button
                    type="button"
                    wire:click="checkForUpdates"
                    wire:loading.attr="disabled"
                    class="w-full py-1.5 px-2.5 text-xs font-semibold text-amber-950 dark:text-amber-200 bg-amber-500/20 hover:bg-amber-500/30 border border-amber-500/40 rounded-lg flex items-center justify-center gap-1.5 transition cursor-pointer"
                >
                    <x-tabler-cloud-download class="w-3.5 h-3.5 text-amber-500" />
                    <span>v{{ $updateAvailable['version'] }} disponible</span>
                </button>
            </div>
        @else
            <div class="pt-1 flex items-center justify-between gap-1">
                <button
                    type="button"
                    wire:click="checkForUpdates"
                    wire:loading.attr="disabled"
                    title="Vérifier si une mise à jour est disponible"
                    class="w-full py-1.5 px-2.5 text-xs font-medium text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 bg-white dark:bg-gray-800/80 hover:bg-gray-100 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-700/80 rounded-lg flex items-center justify-center gap-1.5 transition shadow-2xs disabled:opacity-50 cursor-pointer"
                >
                    <x-tabler-refresh class="w-3.5 h-3.5 text-gray-400" wire:loading.class="animate-spin text-indigo-500" wire:target="checkForUpdates" />
                    <span wire:loading.remove wire:target="checkForUpdates">Rechercher une mise à jour</span>
                    <span wire:loading wire:target="checkForUpdates">Vérification en cours...</span>
                </button>
            </div>
        @endif
    </div>

    <!-- Sidebar Repliée (Collapsed) -->
    <div 
        x-show="! $store.sidebar.isOpen"
        class="py-2 flex justify-center"
    >
        <button
            type="button"
            wire:click="checkForUpdates"
            wire:loading.attr="disabled"
            title="LinksVault v{{ $currentVersion }} — Cliquez pour rechercher une mise à jour"
            class="relative inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
        >
            <x-tabler-refresh class="w-4 h-4" wire:loading.class="animate-spin text-indigo-500" wire:target="checkForUpdates" />
            <span class="absolute top-1 right-1 w-1.5 h-1.5 rounded-full {{ $updateDownloaded ? 'bg-emerald-400 animate-ping' : ($updateAvailable ? 'bg-amber-400' : 'bg-emerald-400') }}"></span>
        </button>
    </div>
</div>
