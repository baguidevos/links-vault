@desktop
{{--
    SOLUTION 2 : Barre de titre personnalisée (Custom Titlebar HTML/CSS)
    Utilisée conjointement avec `Window::open()->titleBarHidden()` dans NativeAppServiceProvider.
--}}
<div 
    id="desktop-custom-titlebar"
    style="-webkit-app-region: drag;"
    class="sticky top-0 z-50 flex items-center w-full h-9 px-3 bg-white/95 dark:bg-gray-900/95 backdrop-blur border-b border-gray-200/80 dark:border-gray-800/80 select-none text-xs text-gray-700 dark:text-gray-300 transition-colors"
>
    <!-- Gauche : Boutons de navigation et Actions -->
    <div class="flex items-center gap-1.5" style="-webkit-app-region: no-drag;">
        <!-- Bouton Retour / Précédent -->
        <button
            type="button"
            onclick="window.history.length > 1 ? window.history.back() : window.location.assign('{{ url('/app') }}');"
            title="Page précédente (Alt + ←)"
            class="inline-flex items-center justify-center w-7 h-7 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-800 transition active:scale-95 cursor-pointer"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
        </button>

        {{-- <!-- Bouton Suivant -->
        <button
            type="button"
            onclick="window.history.forward();"
            title="Page suivante (Alt + →)"
            class="inline-flex items-center justify-center w-7 h-7 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-800 transition active:scale-95 cursor-pointer"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
            </svg>
        </button> --}}

        <!-- Bouton Actualiser -->
        {{-- <button
            type="button"
            onclick="window.location.reload();"
            title="Actualiser la page (F5 / Ctrl + R)"
            class="inline-flex items-center justify-center w-7 h-7 rounded-md text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-800 transition active:scale-95 cursor-pointer"
        >
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
            </svg>
        </button> --}}
    </div>

    <!-- Centre : Titre de l'application -->
    <div class="flex items-center gap-1.5 font-semibold text-gray-800 dark:text-gray-200 pointer-events-none">
        <img src="{{ asset('favicon-96x96.png') }}" alt="LinksVault" class="w-3.5 h-3.5 rounded" />
        <span class="tracking-wide text-[11px]">LinksVault</span>
    </div>

    <!-- Droite : Espace pour préserver la marge des boutons Windows (minimiser/fermer) -->
    <div class="w-24"></div>
</div>
@enddesktop
