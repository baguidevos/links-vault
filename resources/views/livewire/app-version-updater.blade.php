<div class="w-full">
    <!-- Sidebar Ouverte (Expanded) -->
    <div 
        x-show="$store.sidebar.isOpen"
        class="p-3 mx-2 my-1.5 rounded-2xl border border-gray-200/80 dark:border-white/10 bg-gradient-to-b from-gray-50/90 to-gray-100/90 dark:from-gray-900/80 dark:to-gray-900/40 backdrop-blur-md ring-1 ring-black/[0.03] dark:ring-white/[0.05] shadow-xs space-y-2.5 transition-all duration-200 hover:border-gray-300/80 dark:hover:border-white/15"
    >
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0">
                <span class="relative flex h-2 w-2 shrink-0">
                    @if ($patchInstalled || $updateDownloaded)
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.7)]"></span>
                    @elseif ($patchAvailable || $updateAvailable)
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-400 shadow-[0_0_8px_rgba(251,191,36,0.7)]"></span>
                    @else
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.7)]"></span>
                    @endif
                </span>
                
                <span class="text-xs font-semibold text-gray-800 dark:text-gray-100 tracking-tight truncate">
                    LinksVault
                </span>
            </div>

            <span class="px-2 py-0.5 text-[10.5px] font-mono font-medium rounded-md bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20 dark:bg-sky-400/10 dark:border-sky-400/20 shadow-2xs">
                v{{ $currentVersion }}
            </span>
        </div>

        @if ($downloadProgress)
            <div class="pt-1 space-y-1.5" wire:poll.1s="refreshProgress">
                <div class="flex items-center justify-between text-[11px] font-medium text-gray-700 dark:text-gray-300">
                    <span class="flex items-center gap-1.5">
                        <x-tabler-cloud-download class="w-3.5 h-3.5 text-sky-500 animate-bounce" />
                        <span>Téléchargement...</span>
                    </span>
                    <span class="font-mono text-sky-600 dark:text-sky-400 font-bold">{{ $downloadProgress['percent'] }}%</span>
                </div>

                <div class="w-full bg-gray-200/80 dark:bg-white/10 rounded-full h-1.5 overflow-hidden shadow-inner">
                    <div 
                        class="bg-gradient-to-r from-sky-500 to-blue-600 dark:from-sky-400 dark:to-blue-500 h-full rounded-full transition-all duration-300 ease-out" 
                        style="width: {{ $downloadProgress['percent'] }}%"
                    ></div>
                </div>

                <div class="flex items-center justify-between text-[10px] text-gray-500 dark:text-gray-400 font-mono">
                    <span>{{ $downloadProgress['humanTransferred'] }} / {{ $downloadProgress['humanTotal'] }}</span>
                    <span>{{ $downloadProgress['humanSpeed'] }}</span>
                </div>
            </div>
        @elseif ($patchInstalled)
            <div class="pt-0.5">
                <button
                    type="button"
                    wire:click="relaunchApp"
                    class="w-full py-1.5 px-2.5 text-xs font-semibold text-white bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 active:scale-[0.98] rounded-xl flex items-center justify-center gap-1.5 transition-all duration-150 shadow-xs shadow-emerald-500/25 cursor-pointer"
                >
                    <x-tabler-refresh class="w-3.5 h-3.5" />
                    <span>Redémarrer l'application</span>
                </button>
            </div>
        @elseif ($patchAvailable)
            <div class="pt-0.5">
                <button
                    type="button"
                    wire:click="downloadAndApplyPatch"
                    wire:loading.attr="disabled"
                    class="w-full py-1.5 px-2.5 text-xs font-semibold text-sky-950 dark:text-sky-100 bg-sky-500/20 hover:bg-sky-500/30 border border-sky-500/40 rounded-xl flex items-center justify-center gap-1.5 transition-all duration-150 active:scale-[0.98] cursor-pointer shadow-xs shadow-sky-500/10"
                >
                    <x-tabler-bolt class="w-3.5 h-3.5 text-sky-500 dark:text-sky-400" wire:loading.remove wire:target="downloadAndApplyPatch" />
                    <x-tabler-refresh class="w-3.5 h-3.5 text-sky-500 dark:text-sky-400 animate-spin" wire:loading wire:target="downloadAndApplyPatch" />
                    <span wire:loading.remove wire:target="downloadAndApplyPatch">Installer le patch v{{ $patchAvailable['version'] }} ({{ $patchAvailable['patch_size_human'] }})</span>
                    <span wire:loading wire:target="downloadAndApplyPatch">Installation en cours...</span>
                </button>
            </div>
        @elseif ($updateDownloaded)
            <div class="pt-0.5">
                <button
                    type="button"
                    wire:click="installUpdate"
                    class="w-full py-1.5 px-2.5 text-xs font-semibold text-white bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 active:scale-[0.98] rounded-xl flex items-center justify-center gap-1.5 transition-all duration-150 shadow-xs shadow-emerald-500/25 cursor-pointer"
                >
                    <x-tabler-refresh class="w-3.5 h-3.5" />
                    <span>Redémarrer & Installer</span>
                </button>
            </div>
        @elseif ($updateAvailable)
            <div class="pt-0.5">
                <button
                    type="button"
                    wire:click="checkForUpdates"
                    wire:loading.attr="disabled"
                    class="w-full py-1.5 px-2.5 text-xs font-semibold text-amber-950 dark:text-amber-100 bg-amber-500/20 hover:bg-amber-500/30 border border-amber-500/40 rounded-xl flex items-center justify-center gap-1.5 transition-all duration-150 active:scale-[0.98] cursor-pointer shadow-xs shadow-amber-500/10"
                >
                    <x-tabler-cloud-download class="w-3.5 h-3.5 text-amber-500" />
                    <span>v{{ $updateAvailable['version'] }} disponible</span>
                </button>
            </div>
        @else
            <div class="pt-0.5">
                <button
                    type="button"
                    wire:click="checkForUpdates"
                    wire:loading.attr="disabled"
                    title="Vérifier si une mise à jour est disponible"
                    class="group relative w-full py-1.5 px-2.5 text-xs font-medium text-gray-700 dark:text-gray-200 hover:text-sky-600 dark:hover:text-white bg-white/80 dark:bg-white/[0.05] hover:bg-white dark:hover:bg-white/[0.1] border border-gray-200/90 dark:border-white/10 hover:border-sky-500/40 dark:hover:border-sky-400/30 rounded-xl flex items-center justify-center gap-2 transition-all duration-200 shadow-2xs active:scale-[0.98] disabled:opacity-50 cursor-pointer"
                >
                    <x-tabler-refresh class="w-3.5 h-3.5 text-gray-400 dark:text-gray-400 group-hover:text-sky-500 dark:group-hover:text-sky-400 transition-transform duration-500 group-hover:rotate-180" wire:loading.class="animate-spin !rotate-0 text-sky-500" wire:target="checkForUpdates" />
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
            wire:click="{{ $patchInstalled ? 'relaunchApp' : ($patchAvailable ? 'downloadAndApplyPatch' : ($updateDownloaded ? 'installUpdate' : 'checkForUpdates')) }}"
            wire:loading.attr="disabled"
            title="LinksVault v{{ $currentVersion }} — {{ $downloadProgress ? 'Téléchargement '.$downloadProgress['percent'].'%' : ($patchInstalled ? 'Redémarrer' : ($patchAvailable ? 'Patch disponible' : 'Rechercher une mise à jour')) }}"
            class="group relative inline-flex items-center justify-center w-8 h-8 rounded-xl text-gray-500 hover:text-sky-600 dark:text-gray-400 dark:hover:text-sky-400 hover:bg-gray-100 dark:hover:bg-white/10 transition-all duration-150 cursor-pointer active:scale-95"
        >
            <x-tabler-refresh class="w-4 h-4 {{ $downloadProgress ? 'animate-spin text-sky-500' : 'group-hover:rotate-180 transition-transform duration-500' }}" wire:loading.class="animate-spin text-sky-500" />
            <span class="absolute top-1 right-1 w-2 h-2 rounded-full {{ $downloadProgress ? 'bg-sky-500 animate-ping' : ($patchInstalled || $updateDownloaded ? 'bg-emerald-400 animate-ping' : ($patchAvailable || $updateAvailable ? 'bg-amber-400 shadow-[0_0_6px_rgba(251,191,36,0.8)]' : 'bg-emerald-400 shadow-[0_0_6px_rgba(52,211,153,0.8)]')) }}"></span>
        </button>
    </div>
</div>
