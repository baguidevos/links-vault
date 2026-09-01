<x-filament-panels::page>
    @php
        $curr = $currency ?? 'EUR';
        $currSymbol = match($curr) {
            'XOF', 'XAF' => 'FCFA',
            'USD' => '$',
            default => '€',
        };
        $isYearly = $billingPeriod === 'yearly';
        $currentPlanSlug = $currentPlan?->slug ?? 'free';
    @endphp

    <div class="space-y-8">
        {{-- 1. CARTE DE L'ABONNEMENT ACTUEL & JAUGES DE QUOTAS --}}
        <div class="overflow-hidden bg-white border border-gray-200/80 shadow-xs rounded-2xl dark:bg-gray-900 dark:border-gray-800">
            <div class="p-6 sm:p-8 bg-gradient-to-r from-primary-500/10 via-primary-500/5 to-transparent border-b border-gray-200/80 dark:border-gray-800">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2.5">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                                {{ __('Plan Actuel :') }} <span class="text-primary-600 dark:text-primary-400">{{ $currentPlan?->name['fr'] ?? $currentPlan?->name['en'] ?? ucfirst($currentPlanSlug) }}</span>
                            </h2>
                            @if ($stats['is_trial'] ?? false)
                                <span class="px-2.5 py-0.5 text-xs font-semibold text-amber-700 bg-amber-100 rounded-full dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300 dark:border-amber-800">
                                    {{ __('Période d\'essai') }}
                                </span>
                            @else
                                <span class="px-2.5 py-0.5 text-xs font-semibold text-emerald-700 bg-emerald-100 rounded-full dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                                    {{ __('Actif') }}
                                </span>
                            @endif
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $currentPlan?->description['fr'] ?? $currentPlan?->description['en'] ?? '' }}
                        </p>
                    </div>

                    <div class="text-left sm:text-right">
                        @if ($currentPlanSlug !== 'free')
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500 dark:text-gray-400">
                                <x-filament::icon icon="heroicon-o-calendar" class="w-4 h-4" />
                                <span>{{ __('Renouvellement automatique') }}</span>
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                <x-filament::icon icon="heroicon-o-check-circle" class="w-4 h-4" />
                                <span>{{ __('Gratuit à vie') }}</span>
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Jauges d'utilisation des fonctionnalités --}}
            <div class="p-6 sm:p-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                {{-- Jauge Liens --}}
                <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-200/60 dark:border-gray-700/60 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                            <x-filament::icon icon="heroicon-m-link" class="w-4 h-4 text-primary-500" />
                            <span>{{ __('Liens sauvegardés') }}</span>
                        </span>
                        <span class="text-xs font-bold text-gray-900 dark:text-white">
                            {{ $stats['links']['used'] ?? 0 }} / {{ $stats['links']['unlimited'] ? '∞' : ($stats['links']['limit'] ?? 100) }}
                        </span>
                    </div>
                    <div class="w-full h-2.5 bg-gray-200 rounded-full dark:bg-gray-700 overflow-hidden">
                        <div 
                            class="h-full rounded-full transition-all duration-500 {{ ($stats['links']['percentage'] ?? 0) > 85 ? 'bg-amber-500' : 'bg-primary-600' }}"
                            style="width: {{ $stats['links']['unlimited'] ? 10 : ($stats['links']['percentage'] ?? 0) }}%"
                        ></div>
                    </div>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400">
                        @if ($stats['links']['unlimited'])
                            {{ __('Stockage illimité de favoris activé.') }}
                        @else
                            {{ __(':count lien(s) restant(s) avant la limite.', ['count' => $stats['links']['remaining'] ?? 0]) }}
                        @endif
                    </p>
                </div>

                {{-- Jauge Résumés IA --}}
                <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-200/60 dark:border-gray-700/60 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                            <x-filament::icon icon="heroicon-m-sparkles" class="w-4 h-4 text-purple-500" />
                            <span>{{ __('Résumés IA du mois') }}</span>
                        </span>
                        <span class="text-xs font-bold text-gray-900 dark:text-white">
                            {{ $stats['ai_summaries']['used'] ?? 0 }} / {{ $stats['ai_summaries']['unlimited'] ? '2 000' : ($stats['ai_summaries']['limit'] ?? 10) }}
                        </span>
                    </div>
                    <div class="w-full h-2.5 bg-gray-200 rounded-full dark:bg-gray-700 overflow-hidden">
                        <div 
                            class="h-full bg-purple-600 rounded-full transition-all duration-500"
                            style="width: {{ $stats['ai_summaries']['percentage'] ?? 0 }}%"
                        ></div>
                    </div>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400">
                        {{ __('Réinitialisation automatique au 1er du mois.') }}
                    </p>
                </div>

                {{-- Jauge Membres & Cloud --}}
                <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-200/60 dark:border-gray-700/60 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                            <x-filament::icon icon="heroicon-m-user-group" class="w-4 h-4 text-emerald-500" />
                            <span>{{ __('Membres d\'équipe') }}</span>
                        </span>
                        <span class="text-xs font-bold text-gray-900 dark:text-white">
                            {{ $stats['team_members']['used'] ?? 1 }} / {{ $stats['team_members']['limit'] ?? 1 }}
                        </span>
                    </div>
                    <div class="w-full h-2.5 bg-gray-200 rounded-full dark:bg-gray-700 overflow-hidden">
                        <div 
                            class="h-full bg-emerald-600 rounded-full transition-all duration-500"
                            style="width: {{ $stats['team_members']['percentage'] ?? 0 }}%"
                        ></div>
                    </div>
                    <div class="flex items-center justify-between text-[11px] text-gray-500 dark:text-gray-400 pt-0.5">
                        <span>{{ __('Cloud Drive :') }}</span>
                        <span class="font-semibold {{ ($stats['cloud_backup'] ?? false) ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}">
                            {{ ($stats['cloud_backup'] ?? false) ? __('Inclus ✓') : __('Non inclus') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. EN-TÊTE GRILLE TARIFAIRE & SÉLECTEURS DE FRÉQUENCE / DEVISE --}}
        <div class="space-y-6">
            <div class="text-center max-w-2xl mx-auto space-y-2">
                <h3 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl dark:text-white">
                    {{ __('Choisissez le plan adapté à vos besoins') }}
                </h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Passez à la vitesse supérieure avec des résumés IA illimités, la sauvegarde Google Drive et la collaboration d\'équipe.') }}
                </p>
            </div>

            {{-- Barres d'options : Mensuel / Annuel & Devises --}}
            <div class="flex flex-wrap items-center justify-center gap-4">
                {{-- Sélecteur Mensuel / Annuel --}}
                <div class="inline-flex p-1 bg-gray-100 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                    <button 
                        type="button" 
                        wire:click="setBillingPeriod('monthly')"
                        @class([
                            'px-4 py-1.5 text-xs font-semibold rounded-lg transition-all',
                            'bg-white text-gray-900 shadow-xs dark:bg-gray-700 dark:text-white' => ! $isYearly,
                            'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' => $isYearly,
                        ])
                    >
                        {{ __('Facturation Mensuelle') }}
                    </button>
                    <button 
                        type="button" 
                        wire:click="setBillingPeriod('yearly')"
                        @class([
                            'px-4 py-1.5 text-xs font-semibold rounded-lg transition-all flex items-center gap-1.5',
                            'bg-white text-gray-900 shadow-xs dark:bg-gray-700 dark:text-white' => $isYearly,
                            'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' => ! $isYearly,
                        ])
                    >
                        <span>{{ __('Facturation Annuelle') }}</span>
                        <span class="px-1.5 py-0.2 text-[10px] font-bold text-emerald-700 bg-emerald-100 rounded-full dark:bg-emerald-950/60 dark:text-emerald-300">
                            -20%
                        </span>
                    </button>
                </div>

                {{-- Sélecteur de Devise --}}
                <div class="inline-flex p-1 bg-gray-100 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                    @foreach (['EUR' => '€ EUR', 'XOF' => 'FCFA (Afrique)', 'USD' => '$ USD'] as $code => $label)
                        <button 
                            type="button" 
                            wire:click="setCurrency('{{ $code }}')"
                            @class([
                                'px-3 py-1.5 text-xs font-semibold rounded-lg transition-all',
                                'bg-white text-gray-900 shadow-xs dark:bg-gray-700 dark:text-white' => $curr === $code,
                                'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' => $curr !== $code,
                            ])
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- 3. GRILLE DES 3 CARTES DE PLANS --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-stretch pt-2">
                @foreach ($plans as $plan)
                    @php
                        $slug = $plan->slug;
                        $isCurrent = $currentPlanSlug === $slug;
                        $isFeatured = $plan->featured || $slug === 'pro';

                        // Calcul du prix dynamique selon la devise et la fréquence
                        $prices = is_array($plan->prices) ? $plan->prices : [];
                        $basePrice = (float) ($prices[$curr] ?? $plan->price ?? 0);

                        if ($isYearly && $basePrice > 0) {
                            // 10 mois facturés pour 12 mois (2 mois gratuits)
                            $calculatedPrice = round(($basePrice * 10) / 12, ($curr === 'XOF' ? 0 : 2));
                            $totalYearly = $basePrice * 10;
                        } else {
                            $calculatedPrice = $basePrice;
                            $totalYearly = $basePrice * 12;
                        }

                        $formattedPrice = match($curr) {
                            'XOF', 'XAF' => number_format($calculatedPrice, 0, ',', ' ') . ' FCFA',
                            'USD' => '$' . number_format($calculatedPrice, 2),
                            default => number_format($calculatedPrice, 2, ',', ' ') . ' €',
                        };
                    @endphp

                    <div 
                        @class([
                            'relative flex flex-col justify-between p-6 sm:p-8 rounded-2xl transition-all duration-200',
                            'bg-white dark:bg-gray-900 border-2 border-primary-500 shadow-xl shadow-primary-500/10 scale-[1.02] z-10' => $isFeatured,
                            'bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700 shadow-xs' => ! $isFeatured,
                        ])
                    >
                        @if ($isFeatured)
                            <div class="absolute -top-3.5 left-1/2 -translate-x-1/2 z-20 whitespace-nowrap">
                                <span class="inline-flex items-center justify-center px-4 py-1 text-[11px] font-bold tracking-wider uppercase text-white bg-primary-600 rounded-full shadow-md whitespace-nowrap">
                                    {{ __('Le Plus Populaire') }}
                                </span>
                            </div>
                        @endif

                        <div class="space-y-6">
                            {{-- Header Plan --}}
                            <div class="space-y-2">
                                <h4 class="text-xl font-bold text-gray-900 dark:text-white">
                                    {{ $plan->name['fr'] ?? $plan->name['en'] ?? ucfirst($slug) }}
                                </h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 min-h-[36px]">
                                    {{ $plan->description['fr'] ?? $plan->description['en'] ?? '' }}
                                </p>
                            </div>

                            {{-- Prix --}}
                            <div class="space-y-1">
                                <div class="flex items-baseline gap-1">
                                    <span class="text-3xl sm:text-4xl font-extrabold tracking-tight text-gray-900 dark:text-white">
                                        {{ $formattedPrice }}
                                    </span>
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                        / {{ __('mois') }}
                                    </span>
                                </div>
                                @if ($isYearly && $basePrice > 0)
                                    <p class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">
                                        {{ __('Facturé :total/an (2 mois offerts !)', ['total' => ($curr === 'XOF' ? number_format($totalYearly, 0, ',', ' ') . ' FCFA' : number_format($totalYearly, 2) . ' ' . $currSymbol)]) }}
                                    </p>
                                @endif
                            </div>

                            <hr class="border-gray-100 dark:border-gray-800" />

                            {{-- Liste des Fonctionnalités --}}
                            <ul class="space-y-3 text-xs text-gray-700 dark:text-gray-300">
                                @if ($slug === 'free')
                                    <li class="flex items-center gap-2.5">
                                        <x-filament::icon icon="heroicon-m-check" class="w-4 h-4 text-emerald-500 shrink-0" />
                                        <span><strong>100 liens</strong> {{ __('sauvegardés au maximum') }}</span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <x-filament::icon icon="heroicon-m-check" class="w-4 h-4 text-emerald-500 shrink-0" />
                                        <span><strong>10 résumés IA</strong> {{ __('par mois') }}</span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <x-filament::icon icon="heroicon-m-check" class="w-4 h-4 text-emerald-500 shrink-0" />
                                        <span>{{ __('Sauvegarde locale & Restauration ZIP') }}</span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <x-filament::icon icon="heroicon-m-check" class="w-4 h-4 text-emerald-500 shrink-0" />
                                        <span>{{ __('Extension Chrome & App Desktop') }}</span>
                                    </li>
                                    <li class="flex items-center gap-2.5 text-gray-400 dark:text-gray-500">
                                        <x-filament::icon icon="heroicon-m-x-mark" class="w-4 h-4 text-gray-400 shrink-0" />
                                        <span class="line-through">{{ __('Sauvegarde Google Drive automatique') }}</span>
                                    </li>
                                    <li class="flex items-center gap-2.5 text-gray-400 dark:text-gray-500">
                                        <x-filament::icon icon="heroicon-m-x-mark" class="w-4 h-4 text-gray-400 shrink-0" />
                                        <span class="line-through">{{ __('Collaboration & Membres d\'équipe') }}</span>
                                    </li>
                                @elseif ($slug === 'pro')
                                    <li class="flex items-center gap-2.5">
                                        <x-filament::icon icon="heroicon-m-check" class="w-4 h-4 text-emerald-500 shrink-0" />
                                        <span><strong>{{ __('Liens Illimités') }}</strong> {{ __('sans restriction') }}</span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <x-filament::icon icon="heroicon-m-check" class="w-4 h-4 text-emerald-500 shrink-0" />
                                        <span><strong>500 résumés IA</strong> {{ __('par mois') }}</span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <x-filament::icon icon="heroicon-m-check" class="w-4 h-4 text-emerald-500 shrink-0" />
                                        <span><strong>{{ __('Google Drive Sync 1-Clic') }}</strong> {{ __('Cloud') }}</span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <x-filament::icon icon="heroicon-m-check" class="w-4 h-4 text-emerald-500 shrink-0" />
                                        <span><strong>{{ __('Scan de santé automatique') }}</strong> {{ __('des liens') }}</span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <x-filament::icon icon="heroicon-m-check" class="w-4 h-4 text-emerald-500 shrink-0" />
                                        <span>{{ __('Jusqu\'à') }} <strong>3 membres</strong> {{ __('collaborateurs') }}</span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <x-filament::icon icon="heroicon-m-check" class="w-4 h-4 text-emerald-500 shrink-0" />
                                        <span>{{ __('Support prioritaire par email') }}</span>
                                    </li>
                                @else
                                    <li class="flex items-center gap-2.5">
                                        <x-filament::icon icon="heroicon-m-check" class="w-4 h-4 text-emerald-500 shrink-0" />
                                        <span><strong>{{ __('Liens Illimités') }}</strong> {{ __('pour toute l\'équipe') }}</span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <x-filament::icon icon="heroicon-m-check" class="w-4 h-4 text-emerald-500 shrink-0" />
                                        <span><strong>2 000 résumés IA</strong> {{ __('par mois') }}</span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <x-filament::icon icon="heroicon-m-check" class="w-4 h-4 text-emerald-500 shrink-0" />
                                        <span><strong>{{ __('Jusqu\'à 20 membres') }}</strong> {{ __('avec rôles avancés') }}</span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <x-filament::icon icon="heroicon-m-check" class="w-4 h-4 text-emerald-500 shrink-0" />
                                        <span>{{ __('Multi-comptes Google Drive Cloud') }}</span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <x-filament::icon icon="heroicon-m-check" class="w-4 h-4 text-emerald-500 shrink-0" />
                                        <span>{{ __('Scan de santé planifié & Détection en masse') }}</span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <x-filament::icon icon="heroicon-m-check" class="w-4 h-4 text-emerald-500 shrink-0" />
                                        <span>{{ __('Gestionnaire de compte dédié') }}</span>
                                    </li>
                                @endif
                            </ul>
                        </div>

                        {{-- Bouton d'action --}}
                        <div class="pt-8">
                            @if ($isCurrent)
                                <button 
                                    type="button" 
                                    disabled
                                    class="w-full py-2.5 px-4 text-xs font-semibold text-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800 rounded-xl cursor-default flex items-center justify-center gap-1.5"
                                >
                                    <x-filament::icon icon="heroicon-m-check" class="w-4 h-4" />
                                    <span>{{ __('Votre Offre Actuelle') }}</span>
                                </button>
                            @else
                                <button 
                                    type="button" 
                                    wire:click="switchPlan('{{ $slug }}')"
                                    wire:loading.attr="disabled"
                                    @class([
                                        'w-full py-2.5 px-4 text-xs font-semibold rounded-xl transition duration-150 shadow-xs cursor-pointer flex items-center justify-center gap-1.5',
                                        'bg-primary-600 hover:bg-primary-500 text-white shadow-primary-500/20' => $isFeatured,
                                        'bg-gray-900 hover:bg-gray-800 text-white dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100' => ! $isFeatured,
                                    ])
                                >
                                    <span>{{ $slug === 'free' ? __('Revenir au plan Gratuit') : __('Choisir le plan :plan', ['plan' => $plan->name['fr'] ?? $slug]) }}</span>
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- 4. FAQ / INFORMATIONS SUR LES PAIEMENTS --}}
        <div class="p-6 sm:p-8 bg-gray-50 dark:bg-gray-800/40 rounded-2xl border border-gray-200 dark:border-gray-700 space-y-4">
            <h4 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <x-filament::icon icon="heroicon-m-shield-check" class="w-5 h-5 text-primary-500" />
                <span>{{ __('Moyens de paiement & Garantie') }}</span>
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs text-gray-600 dark:text-gray-400">
                <div class="space-y-1">
                    <p class="font-semibold text-gray-900 dark:text-white">{{ __('🌍 Mobile Money & Cartes') }}</p>
                    <p>{{ __('Paiements acceptés en FCFA via Wave, MTN MoMo, Orange Money, Moov Money et cartes Visa/Mastercard.') }}</p>
                </div>
                <div class="space-y-1">
                    <p class="font-semibold text-gray-900 dark:text-white">{{ __('🔄 Sans Engagement') }}</p>
                    <p>{{ __('Vous pouvez changer d\'offre ou résilier votre abonnement à tout moment d\'un simple clic.') }}</p>
                </div>
                <div class="space-y-1">
                    <p class="font-semibold text-gray-900 dark:text-white">{{ __('🔒 Données Sécurisées') }}</p>
                    <p>{{ __('Vos données restent toujours votre propriété et sont exportables à tout moment en HTML, JSON ou CSV.') }}</p>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
