@desktop
<div 
    x-data="{
        goBack() {
            window.history.back();
        },
        goForward() {
            window.history.forward();
        },
        reload() {
            window.location.reload();
        }
    }"
    class="flex items-center gap-1 my-auto mr-2"
>
    <button
        type="button"
        x-on:click="goBack()"
        title="Page précédente (Alt + ←)"
        class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-800/80 transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-primary-500/20 active:scale-95 cursor-pointer"
    >
        <x-filament::icon icon="heroicon-m-chevron-left" class="w-5 h-5" />
    </button>

    <button
        type="button"
        x-on:click="goForward()"
        title="Page suivante (Alt + →)"
        class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-800/80 transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-primary-500/20 active:scale-95 cursor-pointer"
    >
        <x-filament::icon icon="heroicon-m-chevron-right" class="w-5 h-5" />
    </button>

    <button
        type="button"
        x-on:click="reload()"
        title="Actualiser la page"
        class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 dark:text-gray-500 dark:hover:text-gray-300 dark:hover:bg-gray-800/80 transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-primary-500/20 active:scale-95 cursor-pointer"
    >
        <x-filament::icon icon="heroicon-m-arrow-path" class="w-4 h-4" />
    </button>
</div>
@enddesktop
