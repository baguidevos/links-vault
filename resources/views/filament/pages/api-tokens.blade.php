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
            class="p-5 bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-transparent border border-amber-500/30 rounded-xl space-y-3 shadow-sm"
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
                    class="flex-1 px-3 py-2 text-sm font-mono bg-white dark:bg-gray-900 border border-amber-500/40 rounded-lg select-all focus:outline-none focus:ring-2 focus:ring-amber-500 text-gray-900 dark:text-gray-100"
                />
                <button 
                    type="button" 
                    x-on:click="copy()"
                    class="px-4 py-2 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-500 rounded-lg flex items-center gap-1.5 transition duration-150 shadow-sm"
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

    <!-- Extension Banner with 1-Click Download -->
    <div class="p-6 bg-gradient-to-r from-indigo-950/40 via-gray-900/40 to-slate-900/40 border border-indigo-500/30 rounded-2xl shadow-sm relative overflow-hidden">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 text-xs font-bold rounded-full bg-indigo-500/20 text-indigo-400 border border-indigo-500/30">
                        Extension Chrome Officielle
                    </span>
                    <span class="text-xs text-gray-400">Manifest V3</span>
                </div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                    Links Vault Clipper pour Google Chrome
                </h3>
                <p class="text-sm text-gray-600 dark:text-gray-300 max-w-xl">
                    Capturez vos articles, vidéos YouTube et documents en 1 clic directement depuis votre navigateur avec détection automatique des métadonnées et résumé IA.
                </p>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <a 
                    href="{{ route('extension.download') }}" 
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm rounded-xl shadow-md transition duration-150 transform hover:-translate-y-0.5"
                >
                    <x-tabler-download class="w-4 h-4" />
                    <span>Télécharger l'Extension (.zip)</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Step-by-step Interactive Installation Guide -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="p-4 bg-white dark:bg-gray-800/80 border border-gray-200 dark:border-gray-700/60 rounded-xl space-y-2 relative shadow-sm">
            <div class="flex items-center justify-between">
                <span class="w-6 h-6 rounded-full bg-indigo-500/10 text-indigo-500 font-bold text-xs flex items-center justify-center">1</span>
                <x-tabler-file-zip class="w-5 h-5 text-gray-400" />
            </div>
            <h4 class="font-semibold text-sm text-gray-900 dark:text-white">Téléchargez & Décompressez</h4>
            <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                Cliquez sur <strong>Télécharger l'Extension</strong> ci-dessus et extrayez le fichier <code>.zip</code> dans un dossier sur votre ordinateur.
            </p>
        </div>

        <div class="p-4 bg-white dark:bg-gray-800/80 border border-gray-200 dark:border-gray-700/60 rounded-xl space-y-2 relative shadow-sm">
            <div class="flex items-center justify-between">
                <span class="w-6 h-6 rounded-full bg-indigo-500/10 text-indigo-500 font-bold text-xs flex items-center justify-center">2</span>
                <x-tabler-browser class="w-5 h-5 text-gray-400" />
            </div>
            <h4 class="font-semibold text-sm text-gray-900 dark:text-white">Activez dans Chrome</h4>
            <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                Ouvrez <code>chrome://extensions</code>, activez le <strong>Mode développeur</strong>, puis cliquez sur <strong>« Charger l'extension non empaquetée »</strong> et sélectionnez le dossier extrait.
            </p>
        </div>

        <div class="p-4 bg-white dark:bg-gray-800/80 border border-gray-200 dark:border-gray-700/60 rounded-xl space-y-2 relative shadow-sm">
            <div class="flex items-center justify-between">
                <span class="w-6 h-6 rounded-full bg-indigo-500/10 text-indigo-500 font-bold text-xs flex items-center justify-center">3</span>
                <x-tabler-key class="w-5 h-5 text-gray-400" />
            </div>
            <h4 class="font-semibold text-sm text-gray-900 dark:text-white">Connectez avec votre Clé</h4>
            <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                Générez une clé API ci-dessous, ouvrez l'extension sur Chrome (raccourci <kbd class="px-1 py-0.5 text-[10px] bg-gray-100 dark:bg-gray-700 rounded font-mono">Alt + S</kbd>), collez la clé et sauvegardez en 1 clic !
            </p>
        </div>
    </div>

    <!-- Tokens Table -->
    {{ $this->table }}
</x-filament-panels::page>
