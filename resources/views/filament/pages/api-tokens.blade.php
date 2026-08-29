<x-filament-panels::page>
    @if ($newlyCreatedToken)
        <div 
            x-data="{ 
                token: '{{ $newlyCreatedToken }}', 
                copied: false,
                copy() {
                    navigator.clipboard.writeText(this.token);
                    this.copied = true;
                    setTimeout(() => this.copied = false, 3000);
                }
            }" 
            class="p-5 bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-transparent border border-amber-500/30 rounded-xl space-y-3"
        >
            <div class="flex items-center gap-2 text-amber-500 font-semibold text-base">
                <x-tabler-key class="w-5 h-5" />
                <span>Nouvelle Clé API Générée</span>
            </div>

            <p class="text-sm text-gray-600 dark:text-gray-300">
                Copiez cette clé immédiatement. Pour des raisons de sécurité, <strong>elle ne sera plus jamais affichée</strong> après avoir rechargé ou quitté cette page.
            </p>

            <div class="flex items-center gap-2 max-w-2xl">
                <input 
                    type="text" 
                    readonly 
                    x-bind:value="token" 
                    class="flex-1 px-3 py-2 text-sm font-mono bg-white dark:bg-gray-900 border border-amber-500/40 rounded-lg select-all focus:outline-none focus:ring-2 focus:ring-amber-500"
                />
                <button 
                    type="button" 
                    x-on:click="copy()"
                    class="px-4 py-2 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-500 rounded-lg flex items-center gap-1.5 transition duration-150"
                >
                    <template x-if="!copied">
                        <span class="flex items-center gap-1.5">
                            <x-tabler-copy class="w-4 h-4" />
                            <span>Copier</span>
                        </span>
                    </template>
                    <template x-if="copied">
                        <span class="flex items-center gap-1.5 text-emerald-100">
                            <x-tabler-check class="w-4 h-4" />
                            <span>Copié !</span>
                        </span>
                    </template>
                </button>
            </div>
        </div>
    @endif

    <!-- Information card for Chrome Extension -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="p-4 bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700/60 rounded-xl flex items-start gap-3">
            <div class="p-2 bg-amber-500/10 text-amber-500 rounded-lg shrink-0">
                <x-tabler-download class="w-5 h-5" />
            </div>
            <div>
                <h4 class="font-semibold text-sm text-gray-900 dark:text-white">1. Installez l'Extension</h4>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Chargez le dossier <code>extension</code> dans <code>chrome://extensions</code> en Mode Développeur.</p>
            </div>
        </div>

        <div class="p-4 bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700/60 rounded-xl flex items-start gap-3">
            <div class="p-2 bg-amber-500/10 text-amber-500 rounded-lg shrink-0">
                <x-tabler-key class="w-5 h-5" />
            </div>
            <div>
                <h4 class="font-semibold text-sm text-gray-900 dark:text-white">2. Générez votre Clé</h4>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Cliquez sur « Générer une clé API » ci-dessus pour obtenir votre token personnel.</p>
            </div>
        </div>

        <div class="p-4 bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700/60 rounded-xl flex items-start gap-3">
            <div class="p-2 bg-amber-500/10 text-amber-500 rounded-lg shrink-0">
                <x-tabler-browser class="w-5 h-5" />
            </div>
            <div>
                <h4 class="font-semibold text-sm text-gray-900 dark:text-white">3. Connectez l'Extension</h4>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Collez votre clé dans l'extension Chrome (onglet « Clé API ») avec l'URL du serveur.</p>
            </div>
        </div>
    </div>

    <!-- Tokens Table -->
    {{ $this->table }}
</x-filament-panels::page>
