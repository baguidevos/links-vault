<x-filament-panels::page>
    @if ($newWebSyncToken)
        <div 
            x-data="{ 
                token: '{{ $newWebSyncToken }}', 
                copied: false,
                copy() {
                    navigator.clipboard.writeText(this.token);
                    this.copied = true;
                    setTimeout(() => this.copied = false, 3000);
                }
            }" 
            class="p-5 bg-gradient-to-r from-emerald-500/10 via-emerald-500/5 to-transparent border border-emerald-500/30 rounded-xl space-y-3 shadow-sm"
        >
            <div class="flex items-center gap-2 text-emerald-500 font-semibold text-base">
                <x-tabler-key class="w-5 h-5" />
                <span>Nouveau Jeton de Synchronisation Desktop</span>
            </div>

            <p class="text-sm text-gray-600 dark:text-gray-300">
                Copiez ce jeton et collez-le dans les paramètres de synchronisation de votre <strong>application Desktop</strong>.
            </p>

            <div class="flex items-center gap-2 max-w-2xl">
                <input 
                    type="text" 
                    readonly 
                    x-bind:value="token" 
                    class="flex-1 px-3 py-2 text-sm font-mono bg-white dark:bg-gray-900 border border-emerald-500/40 rounded-lg select-all focus:outline-none focus:ring-2 focus:ring-emerald-500 text-gray-900 dark:text-gray-100"
                />
                <button 
                    type="button" 
                    x-on:click="copy()"
                    class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-500 rounded-lg flex items-center gap-1.5 transition duration-150 shadow-sm"
                >
                    <template x-if="!copied">
                        <span class="flex items-center gap-1.5">
                            <x-tabler-copy class="w-4 h-4" />
                            <span>Copier</span>
                        </span>
                    </template>
                    <template x-if="copied">
                        <span class="flex items-center gap-1.5 text-white">
                            <x-tabler-check class="w-4 h-4" />
                            <span>Copié !</span>
                        </span>
                    </template>
                </button>
            </div>
        </div>
    @endif

    <!-- Status Banner -->
    <div class="p-6 bg-gradient-to-r from-gray-900/60 via-slate-900/60 to-gray-900/60 border border-gray-800 rounded-2xl shadow-sm relative overflow-hidden">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
            <div class="space-y-2">
                <div class="flex items-center gap-3">
                    @if ($sync_status === 'syncing' || $is_syncing)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-500/20 text-amber-400 border border-amber-500/30">
                            <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                            Synchronisation en cours...
                        </span>
                    @elseif ($sync_status === 'error')
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-rose-500/20 text-rose-400 border border-rose-500/30">
                            <span class="w-2 h-2 rounded-full bg-rose-400"></span>
                            Erreur de synchronisation
                        </span>
                    @elseif (! empty($api_token))
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                            Prêt & Connecté
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-gray-500/20 text-gray-400 border border-gray-500/30">
                            <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                            Non configuré
                        </span>
                    @endif

                    @desktop
                        <span class="px-2.5 py-0.5 text-xs font-medium rounded-md bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                            Environnement Desktop (Local SQLite)
                        </span>
                    @else
                        <span class="px-2.5 py-0.5 text-xs font-medium rounded-md bg-blue-500/10 text-blue-400 border border-blue-500/20">
                            Environnement Web Cloud (Serveur Central)
                        </span>
                    @enddesktop
                </div>

                <h3 class="text-xl font-bold text-white flex items-center gap-2">
                    <x-tabler-refresh class="w-6 h-6 text-indigo-400 {{ $is_syncing ? 'animate-spin' : '' }}" />
                    Synchronisation Bidirectionnelle Delta
                </h3>

                <p class="text-sm text-gray-400 max-w-2xl">
                    Permet d'ajouter ou de modifier des liens sur Desktop (hors-ligne ou en local) et de propager instantanément les deltas sur votre serveur Web central dès qu'une connexion est disponible.
                </p>

                @if ($last_synced_at)
                    <p class="text-xs text-gray-500">
                        Dernière synchronisation réussie : <span class="text-gray-300 font-mono">{{ $last_synced_at }}</span>
                    </p>
                @endif

                @if ($last_error)
                    <p class="text-xs text-rose-400 bg-rose-500/10 border border-rose-500/20 p-2 rounded-lg max-w-xl font-mono">
                        {{ $last_error }}
                    </p>
                @endif
            </div>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                <button 
                    type="button" 
                    wire:click="syncNow" 
                    wire:loading.attr="disabled"
                    @disabled(empty($api_token) || $is_syncing)
                    class="px-5 py-2.5 text-sm font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white shadow-lg shadow-indigo-600/20 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 transition duration-150"
                >
                    <x-tabler-refresh class="w-4 h-4" wire:loading.class="animate-spin" />
                    <span>Synchroniser maintenant</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Configuration Form -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm space-y-6">
            <div class="border-b border-gray-100 dark:border-gray-800 pb-4">
                <h4 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <x-tabler-settings class="w-5 h-5 text-gray-400" />
                    Configuration de la Liaison
                </h4>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Indiquez l'URL de votre instance Web et le jeton d'accès Sanctum généré.
                </p>
            </div>

            <form wire:submit="saveSettings" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        URL du Serveur Web LinksVault
                    </label>
                    <input 
                        type="url" 
                        wire:model="server_url" 
                        placeholder="https://linksvault.app ou https://vault.votre-domaine.com" 
                        class="w-full px-3.5 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                    />
                    @error('server_url') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Jeton d'Accès API (Sanctum Token)
                    </label>
                    <input 
                        type="password" 
                        wire:model="api_token" 
                        placeholder="ex: 1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" 
                        class="w-full px-3.5 py-2 text-sm font-mono rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                    />
                    @error('api_token') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <input 
                        type="checkbox" 
                        id="auto_sync_enabled" 
                        wire:model="auto_sync_enabled" 
                        class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500 w-4 h-4"
                    />
                    <label for="auto_sync_enabled" class="text-sm text-gray-700 dark:text-gray-300 font-medium cursor-pointer">
                        Activer la synchronisation automatique en arrière-plan (au démarrage et récurrent)
                    </label>
                </div>

                <div class="pt-4 flex justify-end">
                    <button 
                        type="submit" 
                        class="px-5 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 rounded-lg shadow-sm transition duration-150"
                    >
                        Enregistrer les modifications
                    </button>
                </div>
            </form>
        </div>

        <!-- Explanations & Architecture Card -->
        <div class="space-y-4">
            <div class="p-5 bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 rounded-2xl space-y-3">
                <div class="flex items-center gap-2 text-indigo-500 font-semibold text-sm">
                    <x-tabler-info-circle class="w-4 h-4" />
                    <span>Comment fonctionne la synchro ?</span>
                </div>
                <ul class="text-xs text-gray-600 dark:text-gray-400 space-y-2 list-disc list-inside leading-relaxed">
                    <li><strong>Push montant :</strong> Vos dossiers, tags et liens créés ou modifiés en local sont transmis au serveur Web.</li>
                    <li><strong>Pull descendant :</strong> Les modifications effectuées depuis d'autres appareils ou l'extension Chrome sont rapatriées.</li>
                    <li><strong>Stratégie LWW :</strong> En cas de conflit d'édition simultanée, l'horodatage le plus récent l'emporte.</li>
                    <li><strong>Pierres tombales :</strong> Les suppressions sont historisées pour être supprimées proprement sur tous vos appareils.</li>
                </ul>
            </div>

            <div class="p-5 bg-gradient-to-br from-indigo-500/5 via-transparent to-purple-500/5 border border-indigo-500/20 rounded-2xl space-y-2">
                <div class="flex items-center gap-2 text-indigo-400 font-semibold text-sm">
                    <x-tabler-shield-lock class="w-4 h-4" />
                    <span>Sécurité & Chiffrement</span>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                    Toutes les requêtes transitent sous protocole sécurisé HTTPS. Les URLs chiffrées en base de données sont déchiffrées et rechiffrées de façon transparente grâce au mécanisme de chiffrement applicatif d'Eloquent.
                </p>
            </div>
        </div>
    </div>
</x-filament-panels::page>
