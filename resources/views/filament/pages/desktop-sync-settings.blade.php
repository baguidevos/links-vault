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
                <div class="flex flex-wrap items-center gap-3">
                    @desktop
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

                        <span class="px-2.5 py-0.5 text-xs font-medium rounded-md bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                            Environnement Desktop (Local SQLite)
                        </span>
                    @else
                        @if ($is_sync_active)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                Synchronisation Active
                            </span>
                        @elseif ($has_ever_synced)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-500/20 text-amber-400 border border-amber-500/30">
                                <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                Synchronisation Inactive
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-500/20 text-blue-400 border border-blue-500/30">
                                <span class="w-2 h-2 rounded-full bg-blue-400"></span>
                                En attente de connexion Desktop
                            </span>
                        @endif

                        <span class="px-2.5 py-0.5 text-xs font-medium rounded-md bg-blue-500/10 text-blue-400 border border-blue-500/20">
                            Environnement Web Cloud (Serveur Central)
                        </span>
                    @enddesktop
                </div>

                <h3 class="text-xl font-bold text-white flex items-center gap-2">
                    <x-tabler-refresh class="w-6 h-6 text-indigo-400 {{ $is_syncing ? 'animate-spin' : '' }}" />
                    Synchronisation Bidirectionnelle Delta
                </h3>

                @desktop
                    <p class="text-sm text-gray-400 max-w-2xl">
                        Permet d'ajouter ou de modifier des liens sur Desktop (hors-ligne ou en local) et de propager instantanément les deltas sur votre serveur Web central dès qu'une connexion est disponible.
                    </p>
                @else
                    <p class="text-sm text-gray-400 max-w-2xl">
                        Ce serveur Web héberge vos données maîtresses et synchronise vos postes Desktop, extensions et applications clientes.
                    </p>
                @enddesktop

                @if ($last_synced_at)
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-400 pt-1">
                        <span>
                            Dernière synchronisation réussie : 
                            <strong class="text-gray-200 font-mono">{{ $last_synced_at }}</strong>
                            @if ($last_synced_diff)
                                <span class="text-indigo-300 font-medium">({{ $last_synced_diff }})</span>
                            @endif
                        </span>

                        @web
                            @if ($is_sync_active)
                                <span class="inline-flex items-center gap-1 text-emerald-400 font-medium">
                                    <x-tabler-circle-check class="w-3.5 h-3.5" />
                                    Active (échanges réguliers détectés)
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-amber-400 font-medium">
                                    <x-tabler-clock class="w-3.5 h-3.5" />
                                    Inactive (aucun échange depuis plus de 7 jours)
                                </span>
                            @endif
                        @endweb
                    </div>
                @else
                    @web
                        <p class="text-xs text-gray-400 pt-1">
                            Aucune synchronisation n'a encore été enregistrée sur ce serveur.
                        </p>
                    @endweb
                @endif

                @if ($last_error)
                    <p class="text-xs text-rose-400 bg-rose-500/10 border border-rose-500/20 p-2 rounded-lg max-w-xl font-mono">
                        {{ $last_error }}
                    </p>
                @endif
            </div>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                @desktop
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
                @else
                    <div class="flex items-center gap-3 px-4 py-3 bg-gray-800/80 border border-gray-700/60 rounded-xl text-sm shadow-inner">
                        <div class="p-2 rounded-lg bg-indigo-500/10 text-indigo-400">
                            <x-tabler-device-desktop class="w-5 h-5" />
                        </div>
                        <div>
                            <div class="text-xs text-gray-400">Clients Desktop appairés</div>
                            <div class="text-sm font-bold text-white">
                                {{ $connected_desktop_clients }} {{ $connected_desktop_clients > 1 ? 'postes connectés' : 'poste connecté' }}
                            </div>
                        </div>
                    </div>
                @enddesktop
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        @desktop
            <!-- Desktop Configuration Form -->
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
        @else
            <!-- Web Cloud Central Hub -->
            <div class="lg:col-span-2 p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm space-y-6">
                <div class="border-b border-gray-100 dark:border-gray-800 pb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <x-tabler-cloud-check class="w-5 h-5 text-indigo-500" />
                            État de la Synchronisation Web Cloud
                        </h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Ce serveur héberge vos données maîtresses et synchronise vos postes Desktop.
                        </p>
                    </div>

                    <div>
                        @if ($is_sync_active)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                Synchro Active
                            </span>
                        @elseif ($has_ever_synced)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                Synchro Inactive
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">
                                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                Jamais synchronisé
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Sync Activity Status Detail Card -->
                <div class="rounded-xl p-4 border {{ $is_sync_active ? 'bg-emerald-500/5 border-emerald-500/20' : ($has_ever_synced ? 'bg-amber-500/5 border-amber-500/20' : 'bg-blue-500/5 border-blue-500/20') }}">
                    <div class="flex items-start gap-3">
                        <div class="p-2 rounded-lg {{ $is_sync_active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : ($has_ever_synced ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400' : 'bg-blue-500/10 text-blue-600 dark:text-blue-400') }}">
                            @if ($is_sync_active)
                                <x-tabler-circle-check class="w-5 h-5" />
                            @elseif ($has_ever_synced)
                                <x-tabler-clock class="w-5 h-5" />
                            @else
                                <x-tabler-info-circle class="w-5 h-5" />
                            @endif
                        </div>
                        <div class="space-y-1 text-sm">
                            <div class="font-semibold {{ $is_sync_active ? 'text-emerald-950 dark:text-emerald-200' : ($has_ever_synced ? 'text-amber-950 dark:text-amber-200' : 'text-blue-950 dark:text-blue-200') }}">
                                @if ($is_sync_active)
                                    La synchronisation a déjà eu lieu et est toujours active
                                @elseif ($has_ever_synced)
                                    Une synchronisation a déjà eu lieu mais est actuellement inactive
                                @else
                                    Aucune synchronisation n'a encore été effectuée
                                @endif
                            </div>
                            <p class="text-xs {{ $is_sync_active ? 'text-emerald-800 dark:text-emerald-300/80' : ($has_ever_synced ? 'text-amber-800 dark:text-amber-300/80' : 'text-blue-800 dark:text-blue-300/80') }} leading-relaxed">
                                @if ($is_sync_active)
                                    Dernière synchronisation réussie le <strong>{{ $last_synced_at }}</strong> ({{ $last_synced_diff }}). Des échanges ont eu lieu au cours des 7 derniers jours : votre serveur et vos clients Desktop sont parfaitement synchronisés.
                                @elseif ($has_ever_synced)
                                    Une synchronisation a déjà été enregistrée le <strong>{{ $last_synced_at }}</strong> ({{ $last_synced_diff }}), mais aucun client Desktop ne s'est synchronisé depuis plus de 7 jours. Assurez-vous que votre application Desktop est ouverte et connectée à Internet.
                                @else
                                    Ce compte Web n'a encore enregistré aucun échange avec l'application Desktop. Générez un jeton d'accès pour connecter votre première application Desktop.
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Parameters for Desktop Connections -->
                <div class="space-y-4 pt-2">
                    <h5 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-tabler-plug class="w-4 h-4 text-indigo-500" />
                        Paramètres de connexion à saisir dans votre application Desktop
                    </h5>

                    <!-- Server URL -->
                    <div 
                        x-data="{ 
                            url: '{{ $current_server_url }}', 
                            copied: false,
                            copy() {
                                navigator.clipboard.writeText(this.url);
                                this.copied = true;
                                setTimeout(() => this.copied = false, 2500);
                            }
                        }"
                        class="space-y-1.5"
                    >
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                            URL du Serveur Web LinksVault
                        </label>
                        <div class="flex items-center gap-2">
                            <input 
                                type="text" 
                                readonly 
                                x-bind:value="url" 
                                class="flex-1 px-3.5 py-2 text-sm font-mono bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg select-all focus:outline-none focus:ring-2 focus:ring-indigo-500 text-gray-900 dark:text-gray-100"
                            />
                            <button 
                                type="button" 
                                x-on:click="copy()"
                                class="px-3.5 py-2 text-xs font-semibold text-gray-700 dark:text-gray-200 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 border border-gray-300 dark:border-gray-700 rounded-lg flex items-center gap-1.5 transition duration-150"
                            >
                                <template x-if="!copied">
                                    <span class="flex items-center gap-1">
                                        <x-tabler-copy class="w-3.5 h-3.5" />
                                        <span>Copier l'URL</span>
                                    </span>
                                </template>
                                <template x-if="copied">
                                    <span class="flex items-center gap-1 text-emerald-500">
                                        <x-tabler-check class="w-3.5 h-3.5" />
                                        <span>Copié !</span>
                                    </span>
                                </template>
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                        <div class="p-3.5 bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700/60 rounded-xl space-y-1">
                            <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">Espace / Équipe actif</span>
                            <div class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-1.5">
                                <x-tabler-users class="w-4 h-4 text-indigo-400" />
                                <span>{{ $active_team_name ?? 'Espace personnel' }}</span>
                            </div>
                        </div>

                        <div class="p-3.5 bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700/60 rounded-xl space-y-1">
                            <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">Postes Desktop configurés</span>
                            <div class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-1.5">
                                <x-tabler-device-desktop class="w-4 h-4 text-indigo-400" />
                                <span>{{ $connected_desktop_clients }} jeton(s) d'accès créé(s)</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3-step Quick Guide -->
                <div class="pt-4 border-t border-gray-100 dark:border-gray-800 space-y-3">
                    <h5 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Comment appairer un nouvel ordinateur Desktop :
                    </h5>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="p-3 bg-gray-50 dark:bg-gray-800/40 border border-gray-200 dark:border-gray-700/40 rounded-xl space-y-1">
                            <div class="flex items-center gap-1.5 text-xs font-bold text-indigo-500">
                                <span class="w-4 h-4 rounded-full bg-indigo-500 text-white flex items-center justify-center text-[10px]">1</span>
                                <span>Ouvrir Desktop</span>
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                Lancez l'application LinksVault sur votre poste et allez dans <em>Paramètres > Synchronisation</em>.
                            </p>
                        </div>

                        <div class="p-3 bg-gray-50 dark:bg-gray-800/40 border border-gray-200 dark:border-gray-700/40 rounded-xl space-y-1">
                            <div class="flex items-center gap-1.5 text-xs font-bold text-indigo-500">
                                <span class="w-4 h-4 rounded-full bg-indigo-500 text-white flex items-center justify-center text-[10px]">2</span>
                                <span>Créer la clé API</span>
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                Cliquez sur <strong>« Générer un jeton de synchro »</strong> en haut à droite pour obtenir un jeton.
                            </p>
                        </div>

                        <div class="p-3 bg-gray-50 dark:bg-gray-800/40 border border-gray-200 dark:border-gray-700/40 rounded-xl space-y-1">
                            <div class="flex items-center gap-1.5 text-xs font-bold text-indigo-500">
                                <span class="w-4 h-4 rounded-full bg-indigo-500 text-white flex items-center justify-center text-[10px]">3</span>
                                <span>Coller et lier</span>
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                Renseignez l'URL du serveur et le jeton. La synchronisation bidirectionnelle démarre !
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @enddesktop

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
