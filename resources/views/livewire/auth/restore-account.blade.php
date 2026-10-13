<div class="w-full max-w-lg mx-auto py-4">
    <!-- Header -->
    <div class="text-center mb-8">
        <div class="inline-flex justify-center mb-4">
            <x-filament::link :href="route('filament.app.auth.login')">
                @include('filament.components.brand-logo')
            </x-filament::link>
        </div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
            Restaurer un compte
        </h1>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            Restaurez votre coffre-fort depuis une archive de sauvegarde <span class="font-semibold text-primary-600 dark:text-primary-400">.zip</span> et connectez-vous directement.
        </p>
    </div>

    <!-- Main Card -->
    <div class="bg-white dark:bg-gray-900/90 shadow-xl rounded-2xl ring-1 ring-gray-950/5 dark:ring-white/10 p-6 sm:p-8 backdrop-blur-xs">
        <form wire:submit.prevent="restore" class="space-y-6">

            <!-- Backup File Input -->
            <div class="space-y-2">
                <label for="backup_file" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                    Fichier de sauvegarde (.zip) <span class="text-danger-500">*</span>
                </label>

                <div 
                    x-data="{ isDragging: false }"
                    x-on:dragover.prevent="isDragging = true"
                    x-on:dragleave.prevent="isDragging = false"
                    x-on:drop="isDragging = false"
                    :class="{ 'border-primary-500 bg-primary-50/50 dark:bg-primary-950/20': isDragging, 'border-gray-300 dark:border-gray-700 hover:border-gray-400 dark:hover:border-gray-600': !isDragging }"
                    class="relative flex flex-col items-center justify-center p-6 border-2 border-dashed rounded-xl transition-colors cursor-pointer bg-gray-50/50 dark:bg-gray-800/40 text-center"
                >
                    <input 
                        type="file" 
                        id="backup_file" 
                        wire:model="backup_file" 
                        accept=".zip,application/zip,application/x-zip-compressed" 
                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                    />

                    <div wire:loading.remove wire:target="backup_file" class="flex flex-col items-center gap-2">
                        <div class="w-12 h-12 rounded-full bg-primary-100 dark:bg-primary-900/40 text-primary-600 dark:text-primary-400 flex items-center justify-center">
                            <x-heroicon-o-arrow-up-tray class="w-6 h-6" />
                        </div>
                        <div>
                            @if ($backup_file)
                                <p class="text-sm font-bold text-gray-900 dark:text-white truncate max-w-xs">
                                    {{ $backup_file->getClientOriginalName() }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ number_format($backup_file->getSize() / 1024, 1) }} Ko - Cliquez pour changer
                                </p>
                            @else
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    <span class="text-primary-600 dark:text-primary-400 font-semibold underline">Cliquez pour importer</span> ou glissez-déposez
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Archive ZIP LinksVault uniquement (max. 50 Mo)
                                </p>
                            @endif
                        </div>
                    </div>

                    <!-- Uploading Spinner -->
                    <div wire:loading wire:target="backup_file" class="flex flex-col items-center gap-2 py-2">
                        <x-filament::loading-indicator class="w-8 h-8 text-primary-600 dark:text-primary-400" />
                        <p class="text-sm font-medium text-primary-600 dark:text-primary-400">
                            Analyse de la sauvegarde en cours...
                        </p>
                    </div>
                </div>

                @error('backup_file')
                    <p class="text-sm font-medium text-danger-600 dark:text-danger-400 flex items-center gap-1.5 mt-1">
                        <x-heroicon-m-exclamation-circle class="w-4 h-4 shrink-0" />
                        <span>{{ $message }}</span>
                    </p>
                @enderror

                @if ($parseError)
                    <div class="p-3.5 rounded-xl bg-danger-50 dark:bg-danger-950/30 border border-danger-200 dark:border-danger-800/50 text-danger-700 dark:text-danger-300 text-sm flex items-start gap-2.5">
                        <x-heroicon-s-x-circle class="w-5 h-5 shrink-0 mt-0.5 text-danger-600 dark:text-danger-400" />
                        <div>
                            <p class="font-semibold">Archive non reconnue</p>
                            <p class="text-xs opacity-90 mt-0.5">{{ $parseError }}</p>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Detected Account Preview (if backup parsed successfully) -->
            @if ($detectedEmail && ! $parseError)
                <div class="p-4 rounded-xl bg-primary-50/70 dark:bg-primary-950/30 border border-primary-200/80 dark:border-primary-800/50 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-primary-700 dark:text-primary-300 flex items-center gap-1.5">
                            <x-heroicon-s-check-badge class="w-4 h-4 text-primary-600 dark:text-primary-400" />
                            Sauvegarde identifiée
                        </span>
                        @if ($detectedLinksCount !== null)
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-primary-200/60 dark:bg-primary-800/60 text-primary-800 dark:text-primary-200">
                                {{ $detectedLinksCount }} lien(s)
                            </span>
                        @endif
                    </div>
                    <div class="text-sm">
                        <p class="font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span>{{ $detectedName }}</span>
                            <span class="text-xs font-normal text-gray-500 dark:text-gray-400">&bull; {{ $detectedEmail }}</span>
                        </p>
                        @if ($detectedTeam)
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">
                                Espace : <span class="font-semibold">{{ $detectedTeam }}</span>
                            </p>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Password Fields -->
            <div class="space-y-4 pt-2 border-t border-gray-100 dark:border-white/5">
                <div class="space-y-1.5">
                    <label for="password" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Nouveau mot de passe <span class="text-danger-500">*</span>
                    </label>
                    <div class="relative">
                        <input 
                            type="password" 
                            id="password" 
                            wire:model="password" 
                            autocomplete="new-password"
                            placeholder="Min. 8 caractères"
                            required
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-xs focus:border-primary-500 focus:ring-primary-500 text-sm px-3.5 py-2.5"
                        />
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Ce mot de passe sécurisera votre compte dès la restauration terminée.
                    </p>
                    @error('password')
                        <p class="text-sm font-medium text-danger-600 dark:text-danger-400 flex items-center gap-1.5 mt-1">
                            <x-heroicon-m-exclamation-circle class="w-4 h-4 shrink-0" />
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                </div>

                <div class="space-y-1.5">
                    <label for="password_confirmation" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Confirmer le mot de passe <span class="text-danger-500">*</span>
                    </label>
                    <input 
                        type="password" 
                        id="password_confirmation" 
                        wire:model="password_confirmation" 
                        autocomplete="new-password"
                        placeholder="Répétez le mot de passe"
                        required
                        class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-xs focus:border-primary-500 focus:ring-primary-500 text-sm px-3.5 py-2.5"
                    />
                </div>
            </div>

            <!-- Submit Button -->
            <div class="pt-2">
                <button 
                    type="submit" 
                    wire:loading.attr="disabled"
                    class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-bold text-white bg-gradient-to-r from-[#0099FF] to-sky-600 hover:from-sky-500 hover:to-indigo-600 focus:ring-4 focus:ring-sky-500/30 shadow-md shadow-sky-500/20 transition-all duration-200 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
                >
                    <span wire:loading.remove wire:target="restore" class="inline-flex items-center gap-2">
                        <x-heroicon-m-arrow-path class="w-5 h-5" />
                        <span>Restaurer et se connecter</span>
                    </span>
                    <span wire:loading wire:target="restore" class="inline-flex items-center gap-2">
                        <x-filament::loading-indicator class="w-5 h-5 text-white" />
                        <span>Restauration en cours...</span>
                    </span>
                </button>
            </div>
        </form>

        <!-- Back to login link -->
        <div class="mt-6 pt-5 border-t border-gray-100 dark:border-white/5 text-center">
            <a 
                href="{{ route('filament.app.auth.login') }}" 
                class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white transition-colors"
            >
                <x-heroicon-m-arrow-left class="w-4 h-4" />
                <span>Retour à la page de connexion</span>
            </a>
        </div>
    </div>
</div>
