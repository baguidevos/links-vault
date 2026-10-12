<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>LinksVault - Smart Bookmark Manager & AI Knowledge Vault</title>
        <meta name="description" content="Sauvegardez, résumez avec l'IA, organisez et sécurisez vos favoris avec détection de liens morts et synchronisation Google Drive.">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">

        <!-- Favicons & Manifest -->
        @include('filament.render_hooks.favicons')

        <!-- Theme initialization to prevent FOUC -->
        <script>
            (function () {
                const savedTheme = localStorage.getItem('linksvault_theme');
                if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            })();
        </script>

        <!-- Styles / Scripts -->
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif

        <style>
            [data-theme-transition] {
                transition: background-color 0.3s ease, border-color 0.3s ease, color 0.3s ease;
            }
        </style>
    </head>
    <body class="bg-[#F8FAFC] dark:bg-[#07090E] text-slate-900 dark:text-slate-100 antialiased overflow-x-hidden transition-colors duration-300 font-sans">

        <!-- ========================================================================= -->
        <!-- NAVIGATION                                                                -->
        <!-- ========================================================================= -->
        <nav class="fixed top-0 left-0 right-0 z-50 bg-white/80 dark:bg-[#07090E]/80 backdrop-blur-xl border-b border-slate-200/80 dark:border-white/5 transition-colors duration-300">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16 sm:h-20">
                    
                    <!-- Brand Logo -->
                    <div class="flex items-center gap-8">
                        <a href="/" class="flex items-center gap-3 group">
                            <div class="relative flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-tr from-[#0099FF] to-sky-400 p-0.5 shadow-lg shadow-sky-500/20 group-hover:scale-105 transition-transform duration-300">
                                <img src="{{ asset('favicon-96x96.png') }}" alt="LinksVault Logo" class="w-full h-full rounded-[10px] object-cover" />
                            </div>
                            <span class="text-xl font-bold tracking-tight font-display bg-gradient-to-r from-slate-900 via-sky-600 to-[#0099FF] dark:from-white dark:via-sky-400 dark:to-indigo-400 bg-clip-text text-transparent">
                                LinksVault
                            </span>
                        </a>

                        <!-- Navigation Links -->
                        <div class="hidden lg:flex items-center gap-8 text-sm font-medium text-slate-600 dark:text-slate-400">
                            <a href="#features" data-i18n="nav_features" class="hover:text-[#0099FF] dark:hover:text-white transition-colors">Fonctionnalités</a>
                            <a href="#extensions" data-i18n="nav_extensions" class="hover:text-[#0099FF] dark:hover:text-white transition-colors">Extensions</a>
                            <a href="#how-it-works" data-i18n="nav_how_it_works" class="hover:text-[#0099FF] dark:hover:text-white transition-colors">Comment ça marche</a>
                            <a href="#pricing" data-i18n="nav_pricing" class="hover:text-[#0099FF] dark:hover:text-white transition-colors">Tarifs</a>
                            <a href="#faq" data-i18n="nav_faq" class="hover:text-[#0099FF] dark:hover:text-white transition-colors">FAQ</a>
                        </div>
                    </div>

                    <!-- Right Controls: Theme + Language + Auth -->
                    <div class="flex items-center gap-3 sm:gap-4">
                        
                        <!-- Language Switcher (FR / EN) -->
                        <div class="inline-flex p-1 bg-slate-200/60 dark:bg-white/5 rounded-xl border border-slate-300/60 dark:border-white/10 text-xs font-semibold">
                            <button type="button" onclick="setLanguage('fr')" id="lang-btn-fr" class="px-2.5 py-1 rounded-lg transition-all text-slate-700 dark:text-slate-300">
                                🇫🇷 FR
                            </button>
                            <button type="button" onclick="setLanguage('en')" id="lang-btn-en" class="px-2.5 py-1 rounded-lg transition-all text-slate-700 dark:text-slate-300">
                                🇬🇧 EN
                            </button>
                        </div>

                        <!-- Dark/Light Theme Toggle -->
                        <button 
                            type="button" 
                            id="theme-toggle"
                            onclick="toggleTheme()" 
                            aria-label="Changer de thème"
                            class="p-2.5 rounded-xl bg-slate-200/60 dark:bg-white/5 border border-slate-300/60 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:text-[#0099FF] dark:hover:text-white transition-colors"
                        >
                            <!-- Sun (shown in dark mode) -->
                            <svg id="theme-sun-icon" class="w-4 h-4 hidden dark:block text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <!-- Moon (shown in light mode) -->
                            <svg id="theme-moon-icon" class="w-4 h-4 block dark:hidden text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                            </svg>
                        </button>

                        <!-- Auth Actions -->
                        @auth
                            <a href="{{ url('/app') }}" data-i18n="nav_dashboard" class="inline-flex items-center gap-2 px-4 sm:px-5 py-2 text-xs sm:text-sm font-semibold rounded-xl bg-gradient-to-r from-[#0099FF] to-sky-500 text-white shadow-md shadow-sky-500/20 hover:opacity-95 transition-all">
                                <span>Tableau de bord</span>
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                </svg>
                            </a>
                        @else
                            <a href="{{ route('filament.app.auth.login') }}" data-i18n="nav_login" class="hidden sm:inline-flex px-4 py-2 text-xs sm:text-sm font-semibold text-slate-700 dark:text-slate-300 hover:text-[#0099FF] dark:hover:text-white transition-colors">
                                Connexion
                            </a>
                            @if (Route::has('filament.app.auth.register'))
                                <a href="{{ route('filament.app.auth.register') }}" data-i18n="nav_register" class="inline-flex items-center gap-2 px-4 sm:px-5 py-2 text-xs sm:text-sm font-semibold rounded-xl bg-slate-900 text-white hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-100 shadow-sm transition-all">
                                    <span>Essai Gratuit</span>
                                </a>
                            @endif
                        @endauth
                    </div>
                </div>
            </div>
        </nav>

        <!-- ========================================================================= -->
        <!-- HERO SECTION                                                              -->
        <!-- ========================================================================= -->
        <section class="relative min-h-[92vh] flex items-center pt-24 sm:pt-32 pb-16 overflow-hidden">
            <!-- Background Radial Glows -->
            <div class="absolute inset-0 pointer-events-none overflow-hidden">
                <div class="absolute -top-24 right-1/4 w-[600px] h-[600px] bg-sky-400/10 dark:bg-[#0099FF]/15 rounded-full blur-[140px]"></div>
                <div class="absolute bottom-0 -left-24 w-[500px] h-[500px] bg-indigo-500/10 dark:bg-purple-600/10 rounded-full blur-[140px]"></div>
            </div>

            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid lg:grid-cols-12 gap-12 lg:gap-8 items-center">
                    
                    <!-- Left Hero Text -->
                    <div class="lg:col-span-7 text-center lg:text-left space-y-8">
                        
                        <!-- Notification Pill Badge -->
                        <div class="inline-flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-sky-50 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-800/60 shadow-xs">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#0099FF] opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-[#0099FF]"></span>
                            </span>
                            <span data-i18n="hero_badge" class="text-xs font-semibold text-sky-700 dark:text-sky-300">
                                ⚡ Abonnements & Mobile Money Wave / Orange / MTN actifs
                            </span>
                        </div>

                        <!-- Main Title -->
                        <h1 class="text-4xl sm:text-6xl lg:text-7xl font-bold tracking-tight font-display leading-[1.08] text-slate-900 dark:text-white">
                            <span data-i18n="hero_title_1">Organisez votre</span><br/>
                            <span class="bg-gradient-to-r from-[#0099FF] via-sky-500 to-indigo-500 bg-clip-text text-transparent" data-i18n="hero_title_2">
                                Univers Numérique
                            </span>
                        </h1>

                        <!-- Subtitle -->
                        <p data-i18n="hero_subtitle" class="text-base sm:text-lg lg:text-xl text-slate-600 dark:text-slate-300 leading-relaxed max-w-2xl mx-auto lg:mx-0">
                            Le gestionnaire intelligent de favoris et bookmarks augmenté par l'IA. Résumés automatiques, scan des liens morts (404), sauvegarde Google Drive 1-clic et extension Chrome instantanée.
                        </p>

                        <!-- CTA Group -->
                        <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start items-center pt-2">
                            <a href="{{ route('filament.app.auth.register') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-8 py-4 rounded-xl text-base font-bold text-white bg-gradient-to-r from-[#0099FF] to-sky-600 hover:from-sky-500 hover:to-sky-700 shadow-xl shadow-sky-500/25 hover:shadow-sky-500/35 transition-all">
                                <span data-i18n="hero_cta_primary">Créer un compte gratuit</span>
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </a>
                            <a href="{{ route('extension.download') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-4 rounded-xl text-base font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 hover:border-sky-400 dark:hover:border-sky-500 shadow-xs transition-all">
                                <svg class="w-5 h-5 text-sky-500" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 0C5.372 0 0 5.373 0 12s5.372 12 12 12 12-5.373 12-12S18.628 0 12 0zm0 4.25c4.28 0 7.75 3.47 7.75 7.75 0 1.09-.23 2.12-.64 3.06l-4.14-7.17A4.246 4.246 0 0012 7.75c-.24 0-.47.03-.7.07L7.68 4.73A7.712 7.712 0 0112 4.25z"/>
                                </svg>
                                <span data-i18n="hero_cta_extension">Extension Chrome</span>
                            </a>
                        </div>

                        <!-- Browser Extensions Status Pill -->
                        <div class="flex flex-wrap items-center justify-center lg:justify-start gap-3 text-xs pt-0.5">
                            <a href="#extensions" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-400 font-semibold hover:opacity-90 transition-opacity">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                <span data-i18n="hero_ext_chrome_status">Chrome : Disponible</span>
                            </a>
                            <a href="#extensions" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-400 font-medium hover:opacity-90 transition-opacity">
                                <span>🦊</span>
                                <span data-i18n="hero_ext_firefox_status">Firefox : Bientôt disponible</span>
                            </a>
                        </div>

                        <!-- Micro Trust Guarantees -->
                        <div class="pt-2 flex flex-wrap items-center justify-center lg:justify-start gap-6 text-xs font-semibold text-slate-500 dark:text-slate-400">
                            <div class="flex items-center gap-1.5">
                                <span class="text-emerald-500 font-bold">✓</span>
                                <span data-i18n="hero_trust_1">100 liens offerts à vie</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="text-emerald-500 font-bold">✓</span>
                                <span data-i18n="hero_trust_2">Sans engagement</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="text-emerald-500 font-bold">✓</span>
                                <span data-i18n="hero_trust_3">Paiements Mobile Money sécurisés</span>
                            </div>
                        </div>

                    </div>

                    <!-- Right Mockup Visual -->
                    <div class="lg:col-span-5 relative">
                        <div class="relative mx-auto max-w-md sm:max-w-lg lg:max-w-none rounded-2xl p-1 bg-gradient-to-b from-sky-400/40 via-sky-400/10 to-transparent shadow-2xl shadow-sky-500/10">
                            <div class="rounded-[14px] bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 p-6 space-y-4">
                                
                                <!-- Mockup Top Header -->
                                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                                    <div class="flex items-center gap-2">
                                        <div class="w-3 h-3 rounded-full bg-rose-500/80"></div>
                                        <div class="w-3 h-3 rounded-full bg-amber-500/80"></div>
                                        <div class="w-3 h-3 rounded-full bg-emerald-500/80"></div>
                                    </div>
                                    <div class="text-[11px] font-semibold text-slate-400 dark:text-slate-500">
                                        app.linksvault.com/links
                                    </div>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300">
                                        Live Vault
                                    </span>
                                </div>

                                <!-- Mockup Item 1 -->
                                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60 space-y-2">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <h4 class="text-xs font-bold text-slate-900 dark:text-white">Laravel 13 Documentation & AI SDK</h4>
                                            <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">laravel.com/docs/ai-sdk</p>
                                        </div>
                                        <span class="px-2 py-0.5 text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300 rounded-full flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> 200 OK
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-600 dark:text-slate-300 bg-white/70 dark:bg-slate-900/60 p-2 rounded-lg border border-slate-200/40 dark:border-slate-800/60">
                                        ✨ <span class="font-medium">Résumé IA :</span> Guide officiel pour l'intégration des modèles LLM et agents autonomes sous PHP 8.4.
                                    </p>
                                    <div class="flex items-center gap-1.5">
                                        <span class="px-2 py-0.5 text-[9px] font-semibold rounded bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300">#AI</span>
                                        <span class="px-2 py-0.5 text-[9px] font-semibold rounded bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300">#Backend</span>
                                        <span class="px-2 py-0.5 text-[9px] font-semibold rounded bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">#Laravel</span>
                                    </div>
                                </div>

                                <!-- Mockup Item 2 -->
                                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60 space-y-2">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <h4 class="text-xs font-bold text-slate-900 dark:text-white">SasPay Developer Hub & Webhooks</h4>
                                            <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">docs.saspay.me/api-reference</p>
                                        </div>
                                        <span class="px-2 py-0.5 text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300 rounded-full flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> 200 OK
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400 pt-1">
                                        <span class="flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-medium">
                                            ✓ Synchronisé Google Drive
                                        </span>
                                        <span>Dossier : 📁 Paiements</span>
                                    </div>
                                </div>

                                <!-- Mockup Bottom Info -->
                                <div class="pt-2 flex items-center justify-between text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                                    <span class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-sky-500 animate-pulse"></span>
                                        Santé globale : 100% opérationnel
                                    </span>
                                    <span class="text-sky-600 dark:text-sky-400 font-bold">LinksVault AI Core</span>
                                </div>

                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- FEATURES BENTO GRID                                                       -->
        <!-- ========================================================================= -->
        <section id="features" class="py-24 sm:py-32 relative border-t border-slate-200/80 dark:border-white/5 bg-white dark:bg-[#07090E]/60 transition-colors duration-300">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Section Header -->
                <div class="text-center max-w-3xl mx-auto mb-16 sm:mb-24 space-y-4">
                    <h2 class="text-xs sm:text-sm font-bold uppercase tracking-wider text-[#0099FF]" data-i18n="feat_tag">
                        Fonctionnalités Clés
                    </h2>
                    <h3 class="text-3xl sm:text-4xl lg:text-5xl font-bold font-display tracking-tight text-slate-900 dark:text-white" data-i18n="feat_title">
                        Conçu pour une productivité sans limite
                    </h3>
                    <p class="text-base sm:text-lg text-slate-600 dark:text-slate-300 leading-relaxed" data-i18n="feat_subtitle">
                        Une suite complète d'outils intelligents pour ne plus jamais égarer une ressource critique sur le web.
                    </p>
                </div>

                <!-- Bento Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    
                    <!-- Feature 1: AI Summaries -->
                    <div class="p-8 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200/80 dark:border-white/10 hover:border-[#0099FF] dark:hover:border-sky-500/50 shadow-xs hover:shadow-xl transition-all duration-300 group">
                        <div class="w-12 h-12 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition-transform">
                            ✨
                        </div>
                        <h4 class="text-xl font-bold mb-3 text-slate-900 dark:text-white" data-i18n="feat_1_title">
                            Résumés & Tags par IA
                        </h4>
                        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed" data-i18n="feat_1_desc">
                            L'IA inspecte le contenu des articles et pages sauvegardées, rédige une synthèse claire et génère les mots-clés pertinents en 1 seconde.
                        </p>
                    </div>

                    <!-- Feature 2: Link Health Scan -->
                    <div class="p-8 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200/80 dark:border-white/10 hover:border-[#0099FF] dark:hover:border-sky-500/50 shadow-xs hover:shadow-xl transition-all duration-300 group">
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition-transform">
                            🩺
                        </div>
                        <h4 class="text-xl font-bold mb-3 text-slate-900 dark:text-white" data-i18n="feat_2_title">
                            Scan de Santé des Liens
                        </h4>
                        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed" data-i18n="feat_2_desc">
                            Fini les favoris expirés : détection proactive des erreurs 404, redirections et sites inaccessibles pour garder une base toujours saine.
                        </p>
                    </div>

                    <!-- Feature 3: Google Drive 1-Click Sync -->
                    <div class="p-8 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200/80 dark:border-white/10 hover:border-[#0099FF] dark:hover:border-sky-500/50 shadow-xs hover:shadow-xl transition-all duration-300 group">
                        <div class="w-12 h-12 rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition-transform">
                            ☁️
                        </div>
                        <h4 class="text-xl font-bold mb-3 text-slate-900 dark:text-white" data-i18n="feat_3_title">
                            Google Drive Sync Cloud
                        </h4>
                        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed" data-i18n="feat_3_desc">
                            Connectez votre compte Google Drive en 1 clic pour des sauvegardes automatiques d'archives ZIP compressées et sécurisées.
                        </p>
                    </div>

                    <!-- Feature 4: Chrome & Firefox Extension -->
                    <div class="p-8 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200/80 dark:border-white/10 hover:border-[#0099FF] dark:hover:border-sky-500/50 shadow-xs hover:shadow-xl transition-all duration-300 group">
                        <div class="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition-transform">
                            🧩
                        </div>
                        <h4 class="text-xl font-bold mb-3 text-slate-900 dark:text-white" data-i18n="feat_4_title">
                            Extension Chrome & Bientôt Firefox
                        </h4>
                        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed" data-i18n="feat_4_desc">
                            Capturez n'importe quelle page web en 1 clic. Extension Chrome disponible immédiatement (compatible Brave, Edge, Arc) et arrivée imminente sur Firefox !
                        </p>
                    </div>

                    <!-- Feature 5: Team Spaces -->
                    <div class="p-8 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200/80 dark:border-white/10 hover:border-[#0099FF] dark:hover:border-sky-500/50 shadow-xs hover:shadow-xl transition-all duration-300 group">
                        <div class="w-12 h-12 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition-transform">
                            👥
                        </div>
                        <h4 class="text-xl font-bold mb-3 text-slate-900 dark:text-white" data-i18n="feat_5_title">
                            Espaces d'Équipe & Collaboration
                        </h4>
                        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed" data-i18n="feat_5_desc">
                            Partagez des dossiers de veille avec vos associés, attribuez des permissions granulaires et collaborez en temps réel.
                        </p>
                    </div>

                    <!-- Feature 6: Mobile Money & Security -->
                    <div class="p-8 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200/80 dark:border-white/10 hover:border-[#0099FF] dark:hover:border-sky-500/50 shadow-xs hover:shadow-xl transition-all duration-300 group">
                        <div class="w-12 h-12 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition-transform">
                            💳
                        </div>
                        <h4 class="text-xl font-bold mb-3 text-slate-900 dark:text-white" data-i18n="feat_6_title">
                            Paiement Local SasPay
                        </h4>
                        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed" data-i18n="feat_6_desc">
                            Abonnez-vous facilement en FCFA via Mobile Money (Wave, MTN, Orange, Moov) ou carte bancaire sans aucune contrainte de devise.
                        </p>
                    </div>

                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- BROWSER EXTENSIONS SECTION (CHROME & FIREFOX)                             -->
        <!-- ========================================================================= -->
        <section id="extensions" class="py-24 relative overflow-hidden bg-white/60 dark:bg-slate-900/40 border-t border-slate-200/80 dark:border-white/5 transition-colors duration-300">
            <!-- Background Radial Glow -->
            <div class="absolute inset-0 pointer-events-none overflow-hidden">
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[300px] bg-sky-500/5 dark:bg-sky-500/10 rounded-full blur-[120px]"></div>
            </div>

            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400 text-xs font-bold uppercase tracking-wider shadow-xs">
                        <span>🧩</span>
                        <span data-i18n="ext_tag">Extensions Navigateurs</span>
                    </div>
                    <h3 class="text-3xl sm:text-5xl font-bold tracking-tight font-display text-slate-900 dark:text-white" data-i18n="ext_title">
                        Capturez le Web à la Vitesse de la Pensée
                    </h3>
                    <p class="text-base sm:text-lg text-slate-600 dark:text-slate-400 leading-relaxed" data-i18n="ext_subtitle">
                        Sauvegardez n'importe quel onglet en 1 clic sans quitter votre page. Disponible immédiatement pour Google Chrome (et Chromium) et très bientôt sur Mozilla Firefox.
                    </p>
                </div>

                <div class="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                    <!-- Google Chrome Card (Available) -->
                    <div class="relative rounded-3xl p-8 bg-white dark:bg-slate-900 border-2 border-sky-500/50 dark:border-sky-500/40 shadow-xl shadow-sky-500/10 flex flex-col justify-between group hover:border-sky-500 transition-all duration-300">
                        <div>
                            <!-- Top status badge -->
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-14 h-14 rounded-2xl bg-sky-50 dark:bg-sky-950/60 flex items-center justify-center p-3 border border-sky-100 dark:border-sky-900/50 shadow-inner">
                                        <!-- Chrome Icon SVG -->
                                        <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none">
                                            <circle cx="12" cy="12" r="4" fill="#4285F4"/>
                                            <path d="M12 2C7.03 2 2.87 5.63 2.11 10.38L8.6 14.13C9.07 10.59 12.06 8 15.68 8h6.21C20.47 4.39 16.53 2 12 2z" fill="#EA4335"/>
                                            <path d="M21.89 10.38H15.4C14.06 10.38 12.87 11.08 12.18 12.15L8.68 5.86C10.22 5.09 11.96 4.67 13.8 4.67c4.66 0 8.58 3.19 9.69 7.46.03.11.05.23.07.35z" fill="#FBBC05"/>
                                            <path d="M12 22c4.34 0 8.08-2.76 9.48-6.62H14.1c-1.34 0-2.53-.7-3.22-1.77L4.38 17.38C6.18 20.19 9.32 22 12 22z" fill="#34A853"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-xl font-bold text-slate-900 dark:text-white">Google Chrome</h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400" data-i18n="ext_chrome_compat">& Navigateurs Chromium</p>
                                    </div>
                                </div>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                    <span data-i18n="ext_status_ready">Disponible</span>
                                </span>
                            </div>

                            <!-- Highlights -->
                            <div class="space-y-3 mb-8 text-sm text-slate-600 dark:text-slate-300">
                                <div class="flex items-start gap-2.5">
                                    <span class="text-sky-500 font-bold mt-0.5">✓</span>
                                    <span data-i18n="ext_chrome_feat1">Capture instantanée en 1-clic de n'importe quel onglet</span>
                                </div>
                                <div class="flex items-start gap-2.5">
                                    <span class="text-sky-500 font-bold mt-0.5">✓</span>
                                    <span data-i18n="ext_chrome_feat2">Sélection du dossier et ajout de tags sans quitter la page</span>
                                </div>
                                <div class="flex items-start gap-2.5">
                                    <span class="text-sky-500 font-bold mt-0.5">✓</span>
                                    <span data-i18n="ext_chrome_feat3">Compatible Brave, Edge, Arc & Opera</span>
                                </div>
                                <div class="flex items-start gap-2.5">
                                    <span class="text-sky-500 font-bold mt-0.5">✓</span>
                                    <span data-i18n="ext_chrome_feat4">Raccourci clavier de capture ultra-rapide</span>
                                </div>
                            </div>
                        </div>

                        <!-- CTA button -->
                        <a href="{{ route('extension.download') }}" class="w-full inline-flex items-center justify-center gap-2.5 px-6 py-3.5 rounded-xl font-bold text-sm text-white bg-gradient-to-r from-[#0099FF] to-sky-600 hover:from-sky-500 hover:to-sky-700 shadow-lg shadow-sky-500/25 transition-all">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            <span data-i18n="ext_chrome_cta">Télécharger l'Extension Chrome (ZIP)</span>
                        </a>
                    </div>

                    <!-- Mozilla Firefox Card (Coming Soon) -->
                    <div class="relative rounded-3xl p-8 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-white/10 shadow-xl shadow-slate-900/5 flex flex-col justify-between">
                        <div>
                            <!-- Top status badge -->
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-14 h-14 rounded-2xl bg-amber-50 dark:bg-amber-950/60 flex items-center justify-center p-3 border border-amber-100 dark:border-amber-900/50 shadow-inner">
                                        <!-- Firefox Icon SVG -->
                                        <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none">
                                            <path d="M12 2C6.48 2 2 6.48 2 12c0 3.84 2.16 7.18 5.34 8.87-.21-.86-.34-1.84-.34-2.87 0-3.14 1.8-5.87 4.41-7.22-.44.82-.7 1.76-.7 2.76 0 2.21 1.25 4.14 3.09 5.12C15.22 17.9 16 16.54 16 15c0-1.86-1.12-3.46-2.73-4.18C15.34 11.23 17 13.41 17 16c0 .48-.06.94-.17 1.38C19.7 15.65 21.5 13.06 21.89 10 21.96 9.4 22 8.79 22 8.17 20.4 4.5 16.5 2 12 2z" fill="#FF7139"/>
                                            <circle cx="14" cy="11" r="3" fill="#FF9400"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-xl font-bold text-slate-900 dark:text-white">Mozilla Firefox</h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400" data-i18n="ext_firefox_compat">Firefox Desktop & Android</p>
                                    </div>
                                </div>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                                    <span data-i18n="ext_status_soon">Bientôt disponible</span>
                                </span>
                            </div>

                            <!-- Highlights -->
                            <div class="space-y-3 mb-8 text-sm text-slate-600 dark:text-slate-300">
                                <div class="flex items-start gap-2.5">
                                    <span class="text-amber-500 font-bold mt-0.5">⚡</span>
                                    <span data-i18n="ext_firefox_feat1">En cours de validation finale pour Firefox Add-ons (AMO)</span>
                                </div>
                                <div class="flex items-start gap-2.5">
                                    <span class="text-amber-500 font-bold mt-0.5">⚡</span>
                                    <span data-i18n="ext_firefox_feat2">Conçu sur le standard WebExtensions moderne</span>
                                </div>
                                <div class="flex items-start gap-2.5">
                                    <span class="text-amber-500 font-bold mt-0.5">⚡</span>
                                    <span data-i18n="ext_firefox_feat3">Respect strict de la confidentialité et zero tracking</span>
                                </div>
                                <div class="flex items-start gap-2.5">
                                    <span class="text-amber-500 font-bold mt-0.5">⚡</span>
                                    <span data-i18n="ext_firefox_feat4">Synchronisation immédiate avec votre compte LinksVault</span>
                                </div>
                            </div>
                        </div>

                        <!-- Disabled preview button -->
                        <div class="w-full inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl font-semibold text-sm text-slate-400 dark:text-slate-500 bg-slate-100 dark:bg-white/5 border border-slate-200/60 dark:border-white/5 cursor-not-allowed">
                            <span data-i18n="ext_firefox_cta">Arrive très prochainement sur Firefox</span>
                            <span class="text-xs">🦊</span>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- HOW IT WORKS                                                              -->
        <!-- ========================================================================= -->
        <section id="how-it-works" class="py-24 sm:py-32 relative border-t border-slate-200/80 dark:border-white/5 bg-[#F8FAFC] dark:bg-[#07090E] transition-colors duration-300">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="text-center max-w-3xl mx-auto mb-16 sm:mb-20 space-y-4">
                    <h2 class="text-xs sm:text-sm font-bold uppercase tracking-wider text-[#0099FF]" data-i18n="step_tag">
                        Simplicité Absolue
                    </h2>
                    <h3 class="text-3xl sm:text-4xl lg:text-5xl font-bold font-display tracking-tight text-slate-900 dark:text-white" data-i18n="step_title">
                        Prêt en moins de 2 minutes
                    </h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 relative">
                    
                    <!-- Step 1 -->
                    <div class="relative p-8 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200/80 dark:border-white/10 shadow-xs text-center space-y-4">
                        <div class="w-14 h-14 mx-auto rounded-full bg-sky-100 text-sky-600 dark:bg-sky-950 dark:text-sky-300 flex items-center justify-center font-bold text-xl font-display">
                            1
                        </div>
                        <h4 class="text-xl font-bold text-slate-900 dark:text-white" data-i18n="step_1_title">1. Capturez en 1-Clic</h4>
                        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed" data-i18n="step_1_desc">
                            Sauvegardez depuis l'extension Chrome, collez une URL ou importez vos favoris HTML existants.
                        </p>
                    </div>

                    <!-- Step 2 -->
                    <div class="relative p-8 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200/80 dark:border-white/10 shadow-xs text-center space-y-4">
                        <div class="w-14 h-14 mx-auto rounded-full bg-purple-100 text-purple-600 dark:bg-purple-950 dark:text-purple-300 flex items-center justify-center font-bold text-xl font-display">
                            2
                        </div>
                        <h4 class="text-xl font-bold text-slate-900 dark:text-white" data-i18n="step_2_title">2. L'IA analyse & résume</h4>
                        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed" data-i18n="step_2_desc">
                            Notre moteur intelligent classe le sujet, extrait les tags clés et vérifie que la page est en ligne.
                        </p>
                    </div>

                    <!-- Step 3 -->
                    <div class="relative p-8 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200/80 dark:border-white/10 shadow-xs text-center space-y-4">
                        <div class="w-14 h-14 mx-auto rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-300 flex items-center justify-center font-bold text-xl font-display">
                            3
                        </div>
                        <h4 class="text-xl font-bold text-slate-900 dark:text-white" data-i18n="step_3_title">3. Retrouvez & synchronisez</h4>
                        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed" data-i18n="step_3_desc">
                            Recherchez par sens ou mot-clé et conservez vos sauvegardes intactes sur Google Drive.
                        </p>
                    </div>

                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- PRICING SECTION                                                           -->
        <!-- ========================================================================= -->
        <section id="pricing" class="py-24 sm:py-32 relative border-t border-slate-200/80 dark:border-white/5 bg-white dark:bg-[#07090E]/60 transition-colors duration-300">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="text-center max-w-3xl mx-auto mb-12 sm:mb-16 space-y-4">
                    <h2 class="text-xs sm:text-sm font-bold uppercase tracking-wider text-[#0099FF]" data-i18n="price_tag">
                        Tarifs Simples & Clairs
                    </h2>
                    <h3 class="text-3xl sm:text-4xl lg:text-5xl font-bold font-display tracking-tight text-slate-900 dark:text-white" data-i18n="price_title">
                        Choisissez l'offre qui vous convient
                    </h3>
                    <p class="text-base sm:text-lg text-slate-600 dark:text-slate-300 leading-relaxed" data-i18n="price_subtitle">
                        Réglez simplement par Mobile Money ou Carte Bancaire via SasPay.
                    </p>

                    <!-- Billing Period Toggle -->
                    <div class="pt-6 inline-flex p-1 bg-slate-100 dark:bg-slate-800/80 rounded-2xl border border-slate-200 dark:border-slate-700">
                        <button 
                            type="button" 
                            id="btn-monthly"
                            onclick="setBillingPeriod('monthly')" 
                            class="px-5 py-2 text-xs font-bold rounded-xl transition-all bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-xs"
                        >
                            <span data-i18n="billing_monthly">Facturation Mensuelle</span>
                        </button>
                        <button 
                            type="button" 
                            id="btn-yearly"
                            onclick="setBillingPeriod('yearly')" 
                            class="px-5 py-2 text-xs font-bold rounded-xl transition-all text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white flex items-center gap-1.5"
                        >
                            <span data-i18n="billing_yearly">Facturation Annuelle</span>
                            <span class="px-2 py-0.5 text-[10px] font-extrabold text-emerald-700 bg-emerald-100 dark:bg-emerald-950 dark:text-emerald-300 rounded-full">
                                -20%
                            </span>
                        </button>
                    </div>
                </div>

                <!-- 3 Pricing Cards Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-stretch pt-4">
                    
                    <!-- 1. FREE PLAN -->
                    <div class="flex flex-col justify-between p-8 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200/80 dark:border-white/10 shadow-xs">
                        <div class="space-y-6">
                            <div>
                                <h4 class="text-2xl font-bold font-display text-slate-900 dark:text-white" data-i18n="plan_free_name">Gratuit</h4>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 min-h-[36px]" data-i18n="plan_free_desc">
                                    Idéal pour découvrir LinksVault et sauvegarder vos premiers favoris personnels.
                                </p>
                            </div>

                            <div class="space-y-1">
                                <div class="flex items-baseline gap-1">
                                    <span class="text-4xl font-extrabold font-display text-slate-900 dark:text-white">0 FCFA</span>
                                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400" data-i18n="price_per_month">/ mois</span>
                                </div>
                                <p class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium" data-i18n="plan_free_badge">
                                    Gratuit à vie sans carte requise
                                </p>
                            </div>

                            <hr class="border-slate-200 dark:border-slate-800" />

                            <ul class="space-y-3 text-xs text-slate-700 dark:text-slate-300">
                                <li class="flex items-center gap-2.5">
                                    <span class="text-emerald-500 font-bold">✓</span>
                                    <span><strong data-i18n="feat_free_1">100 liens</strong> sauvegardés au max</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <span class="text-emerald-500 font-bold">✓</span>
                                    <span><strong data-i18n="feat_free_2">10 résumés IA</strong> par mois</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <span class="text-emerald-500 font-bold">✓</span>
                                    <span data-i18n="feat_free_3">Sauvegarde locale & Restauration ZIP</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <span class="text-emerald-500 font-bold">✓</span>
                                    <span data-i18n="feat_free_4">Extension Chrome & App Desktop</span>
                                </li>
                                <li class="flex items-center gap-2.5 text-slate-400 dark:text-slate-500">
                                    <span class="text-slate-400">✗</span>
                                    <span class="line-through" data-i18n="feat_free_5">Sauvegarde Google Drive automatique</span>
                                </li>
                            </ul>
                        </div>

                        <div class="pt-8">
                            <a href="{{ route('filament.app.auth.register') }}" class="w-full py-3 px-4 text-xs font-bold rounded-xl bg-slate-900 hover:bg-slate-800 text-white dark:bg-white dark:text-slate-950 dark:hover:bg-slate-100 flex items-center justify-center transition-all shadow-xs">
                                <span data-i18n="cta_choose_free">Commencer Gratuitement</span>
                            </a>
                        </div>
                    </div>

                    <!-- 2. PRO PLAN (FEATURED) -->
                    <div class="relative flex flex-col justify-between p-8 rounded-2xl bg-white dark:bg-slate-900 border-2 border-[#0099FF] shadow-2xl shadow-sky-500/15 scale-100 lg:scale-[1.03] z-10">
                        <div class="absolute -top-3.5 left-1/2 -translate-x-1/2">
                            <span class="px-4 py-1 text-[11px] font-bold uppercase tracking-wider text-white bg-gradient-to-r from-[#0099FF] to-sky-600 rounded-full shadow-md" data-i18n="plan_pro_badge">
                                Le Plus Populaire
                            </span>
                        </div>

                        <div class="space-y-6 pt-2">
                            <div>
                                <h4 class="text-2xl font-bold font-display text-slate-900 dark:text-white" data-i18n="plan_pro_name">Pro</h4>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 min-h-[36px]" data-i18n="plan_pro_desc">
                                    Pour les indépendants et professionnels désireux d'une mémoire numérique illimitée enrichie par l'IA.
                                </p>
                            </div>

                            <div class="space-y-1">
                                <div class="flex items-baseline gap-1">
                                    <span id="price-pro" class="text-4xl font-extrabold font-display text-slate-900 dark:text-white">800 FCFA</span>
                                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400" data-i18n="price_per_month">/ mois</span>
                                </div>
                                <p id="billing-pro-sub" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">
                                    Sans engagement • 7 jours d'essai offerts
                                </p>
                            </div>

                            <hr class="border-slate-200 dark:border-slate-800" />

                            <ul class="space-y-3 text-xs text-slate-700 dark:text-slate-300">
                                <li class="flex items-center gap-2.5">
                                    <span class="text-emerald-500 font-bold">✓</span>
                                    <span><strong data-i18n="feat_pro_1">Liens Illimités (∞)</strong> sans restriction</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <span class="text-emerald-500 font-bold">✓</span>
                                    <span><strong data-i18n="feat_pro_2">500 résumés IA</strong> par mois</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <span class="text-emerald-500 font-bold">✓</span>
                                    <span><strong data-i18n="feat_pro_3">Google Drive Sync 1-Clic</strong> Cloud</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <span class="text-emerald-500 font-bold">✓</span>
                                    <span><strong data-i18n="feat_pro_4">Scan de santé automatique</strong> des liens</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <span class="text-emerald-500 font-bold">✓</span>
                                    <span><strong data-i18n="feat_pro_5">Jusqu'à 3 membres</strong> d'équipe</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <span class="text-emerald-500 font-bold">✓</span>
                                    <span data-i18n="feat_pro_6">Support prioritaire par email</span>
                                </li>
                            </ul>
                        </div>

                        <div class="pt-8">
                            <a href="{{ route('filament.app.auth.register') }}" class="w-full py-3.5 px-4 text-xs font-bold rounded-xl bg-gradient-to-r from-[#0099FF] to-sky-600 hover:from-sky-500 hover:to-sky-700 text-white flex items-center justify-center transition-all shadow-md shadow-sky-500/25">
                                <span data-i18n="cta_choose_pro">Essayer le Plan Pro</span>
                            </a>
                        </div>
                    </div>

                    <!-- 3. TEAM PLAN -->
                    <div class="flex flex-col justify-between p-8 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200/80 dark:border-white/10 shadow-xs">
                        <div class="space-y-6">
                            <div>
                                <h4 class="text-2xl font-bold font-display text-slate-900 dark:text-white" data-i18n="plan_team_name">Équipe</h4>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 min-h-[36px]" data-i18n="plan_team_desc">
                                    Collaboration avancée, rôles d'équipe, multi-comptes Cloud et quotas étendus.
                                </p>
                            </div>

                            <div class="space-y-1">
                                <div class="flex items-baseline gap-1">
                                    <span id="price-team" class="text-4xl font-extrabold font-display text-slate-900 dark:text-white">2 000 FCFA</span>
                                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400" data-i18n="price_per_month">/ mois</span>
                                </div>
                                <p id="billing-team-sub" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">
                                    Sans engagement • 14 jours d'essai offerts
                                </p>
                            </div>

                            <hr class="border-slate-200 dark:border-slate-800" />

                            <ul class="space-y-3 text-xs text-slate-700 dark:text-slate-300">
                                <li class="flex items-center gap-2.5">
                                    <span class="text-emerald-500 font-bold">✓</span>
                                    <span><strong data-i18n="feat_team_1">Liens Illimités</strong> pour toute l'équipe</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <span class="text-emerald-500 font-bold">✓</span>
                                    <span><strong data-i18n="feat_team_2">2 000 résumés IA</strong> par mois</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <span class="text-emerald-500 font-bold">✓</span>
                                    <span><strong data-i18n="feat_team_3">Jusqu'à 20 membres</strong> avec rôles</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <span class="text-emerald-500 font-bold">✓</span>
                                    <span data-i18n="feat_team_4">Multi-comptes Google Drive Cloud</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <span class="text-emerald-500 font-bold">✓</span>
                                    <span data-i18n="feat_team_5">Scan de santé planifié & en masse</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <span class="text-emerald-500 font-bold">✓</span>
                                    <span data-i18n="feat_team_6">Gestionnaire de compte dédié</span>
                                </li>
                            </ul>
                        </div>

                        <div class="pt-8">
                            <a href="{{ route('filament.app.auth.register') }}" class="w-full py-3 px-4 text-xs font-bold rounded-xl bg-slate-900 hover:bg-slate-800 text-white dark:bg-white dark:text-slate-950 dark:hover:bg-slate-100 flex items-center justify-center transition-all shadow-xs">
                                <span data-i18n="cta_choose_team">Choisir le Plan Équipe</span>
                            </a>
                        </div>
                    </div>

                </div>

                <!-- Payment Assurance Banner -->
                <div class="mt-12 p-6 rounded-2xl bg-slate-100/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-white/5 flex flex-col sm:flex-row items-center justify-between gap-4 text-center sm:text-left">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl">🌍</span>
                        <div>
                            <h5 class="text-xs font-bold text-slate-900 dark:text-white" data-i18n="pay_banner_title">Paiements Locaux & Internationaux Sécurisés</h5>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400" data-i18n="pay_banner_desc">
                                Encaissement via Mobile Money (Wave, MTN, Orange, Moov) et Cartes Bancaires propulsé par SasPay.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-300">
                        <span class="px-2.5 py-1 rounded bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700">Wave</span>
                        <span class="px-2.5 py-1 rounded bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700">MTN MoMo</span>
                        <span class="px-2.5 py-1 rounded bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700">Orange Money</span>
                        <span class="px-2.5 py-1 rounded bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700">Visa / MC</span>
                    </div>
                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- FAQ SECTION                                                               -->
        <!-- ========================================================================= -->
        <section id="faq" class="py-24 sm:py-32 relative border-t border-slate-200/80 dark:border-white/5 bg-[#F8FAFC] dark:bg-[#07090E] transition-colors duration-300">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="text-center mb-16 space-y-4">
                    <h2 class="text-xs sm:text-sm font-bold uppercase tracking-wider text-[#0099FF]" data-i18n="faq_tag">FAQ</h2>
                    <h3 class="text-3xl sm:text-4xl font-bold font-display tracking-tight text-slate-900 dark:text-white" data-i18n="faq_title">
                        Questions Fréquentes
                    </h3>
                </div>

                <div class="space-y-4">
                    
                    <!-- Item 1 -->
                    <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200/80 dark:border-white/10 shadow-xs">
                        <h4 class="text-base font-bold text-slate-900 dark:text-white mb-2" data-i18n="faq_q1">
                            Puis-je payer avec mon compte Mobile Money (Wave, Orange, MTN) ?
                        </h4>
                        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed" data-i18n="faq_a1">
                            Oui, absolument ! Grâce à notre passerelle partenaire SasPay, vous pouvez souscrire directement en FCFA avec Wave, MTN Mobile Money, Orange Money, Moov Money ou par carte bancaire (Visa, Mastercard).
                        </p>
                    </div>

                    <!-- Item 2 -->
                    <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200/80 dark:border-white/10 shadow-xs">
                        <h4 class="text-base font-bold text-slate-900 dark:text-white mb-2" data-i18n="faq_q2">
                            Comment fonctionne la détection des liens morts ?
                        </h4>
                        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed" data-i18n="faq_a2">
                            Notre scanner vérifie en arrière-plan le code retour HTTP de chacun de vos favoris (200 OK, 301 redirection, 404 introuvable, 500 erreur serveur). Vous êtes alerté dès qu'une ressource n'est plus accessible.
                        </p>
                    </div>

                    <!-- Item 3 -->
                    <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200/80 dark:border-white/10 shadow-xs">
                        <h4 class="text-base font-bold text-slate-900 dark:text-white mb-2" data-i18n="faq_q3">
                            Où sont stockées les sauvegardes Google Drive ?
                        </h4>
                        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed" data-i18n="faq_a3">
                            Les sauvegardes sont envoyées directement sur VOTRE propre compte Google Drive personnel dans un dossier dédié sécurisé sous forme d'archives ZIP téléchargeables à tout moment.
                        </p>
                    </div>

                    <!-- Item 4 -->
                    <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200/80 dark:border-white/10 shadow-xs">
                        <h4 class="text-base font-bold text-slate-900 dark:text-white mb-2" data-i18n="faq_q4">
                            Puis-je exporter mes favoris vers un autre navigateur ?
                        </h4>
                        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed" data-i18n="faq_a4">
                            Oui. Vous pouvez exporter l'intégralité de votre bibliothèque aux formats standards HTML (compatible Chrome, Safari, Firefox, Edge), JSON ou CSV en 1 clic.
                        </p>
                    </div>

                    <!-- Item 5 -->
                    <div class="p-6 rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200/80 dark:border-white/10 shadow-xs">
                        <h4 class="text-base font-bold text-slate-900 dark:text-white mb-2" data-i18n="faq_q5">
                            Sur quels navigateurs l'extension fonctionne-t-elle ?
                        </h4>
                        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed" data-i18n="faq_a5">
                            L'extension LinksVault est disponible immédiatement pour Google Chrome ainsi que tous les navigateurs Chromium (Brave, Microsoft Edge, Opera, Arc). L'extension officielle pour Mozilla Firefox est en cours de développement actif et arrive très prochainement !
                        </p>
                    </div>

                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- FINAL CTA                                                                 -->
        <!-- ========================================================================= -->
        <section class="py-24 sm:py-32 relative border-t border-slate-200/80 dark:border-white/5 bg-gradient-to-b from-sky-50/50 to-white dark:from-slate-900/40 dark:to-[#07090E]">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-8">
                <h2 class="text-3xl sm:text-5xl font-bold font-display tracking-tight text-slate-900 dark:text-white" data-i18n="cta_final_title">
                    Prêt à organiser votre savoir numérique ?
                </h2>
                <p class="text-base sm:text-lg text-slate-600 dark:text-slate-300 max-w-2xl mx-auto" data-i18n="cta_final_desc">
                    Rejoignez les professionnels qui ne perdent plus jamais un lien important. Démarrez gratuitement en 30 secondes.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                    <a href="{{ route('filament.app.auth.register') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-8 py-4 rounded-xl text-base font-bold text-white bg-gradient-to-r from-[#0099FF] to-sky-600 shadow-xl shadow-sky-500/25 hover:shadow-sky-500/35 transition-all">
                        <span data-i18n="cta_final_btn">Créer mon espace gratuitement</span>
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </a>
                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- FOOTER                                                                    -->
        <!-- ========================================================================= -->
        <footer class="py-12 border-t border-slate-200/80 dark:border-white/5 bg-white dark:bg-[#07090E] transition-colors duration-300">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-6 text-xs text-slate-500 dark:text-slate-400">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('favicon-96x96.png') }}" alt="LinksVault" class="w-6 h-6 rounded" />
                    <span class="font-bold text-slate-900 dark:text-white font-display">LinksVault</span>
                    <span>• © {{ date('Y') }} Tous droits réservés.</span>
                </div>
                <div class="flex flex-wrap items-center gap-6">
                    <a href="#features" data-i18n="nav_features" class="hover:text-[#0099FF] dark:hover:text-white transition-colors">Fonctionnalités</a>
                    <a href="#extensions" data-i18n="nav_extensions" class="hover:text-[#0099FF] dark:hover:text-white transition-colors">Extensions</a>
                    <a href="#pricing" data-i18n="nav_pricing" class="hover:text-[#0099FF] dark:hover:text-white transition-colors">Tarifs</a>
                    <a href="{{ route('extension.download') }}" data-i18n="hero_cta_extension" class="hover:text-[#0099FF] dark:hover:text-white transition-colors">Extension Chrome</a>
                    <a href="#extensions" data-i18n="hero_ext_firefox_status" class="hover:text-amber-500 transition-colors">Firefox : Bientôt disponible</a>
                    <a href="{{ route('filament.app.auth.login') }}" data-i18n="nav_login" class="hover:text-[#0099FF] dark:hover:text-white transition-colors">Connexion</a>
                </div>
            </div>
        </footer>

        <!-- ========================================================================= -->
        <!-- CLIENT-SIDE SCRIPTS: THEME, BILINGUAL I18N, AND PRICING TOGGLE            -->
        <!-- ========================================================================= -->
        <script>
            // -----------------------------------------------------------------
            // 1. THEME SWITCHER (LIGHT / DARK)
            // -----------------------------------------------------------------
            function toggleTheme() {
                const isDark = document.documentElement.classList.contains('dark');
                if (isDark) {
                    document.documentElement.classList.remove('dark');
                    localStorage.setItem('linksvault_theme', 'light');
                } else {
                    document.documentElement.classList.add('dark');
                    localStorage.setItem('linksvault_theme', 'dark');
                }
            }

            // -----------------------------------------------------------------
            // 2. BILINGUAL DICTIONARY (FR / EN)
            // -----------------------------------------------------------------
            const translations = {
                fr: {
                    nav_features: "Fonctionnalités",
                    nav_extensions: "Extensions",
                    nav_how_it_works: "Comment ça marche",
                    nav_pricing: "Tarifs",
                    nav_faq: "FAQ",
                    nav_login: "Connexion",
                    nav_register: "Essai Gratuit",
                    nav_dashboard: "Tableau de bord",

                    hero_badge: "⚡ Abonnements & Mobile Money Wave / Orange / MTN actifs",
                    hero_title_1: "Organisez votre",
                    hero_title_2: "Univers Numérique",
                    hero_subtitle: "Le gestionnaire intelligent de favoris et bookmarks augmenté par l'IA. Résumés automatiques, scan des liens morts (404), sauvegarde Google Drive 1-clic et extension Chrome instantanée.",
                    hero_cta_primary: "Créer un compte gratuit",
                    hero_cta_extension: "Extension Chrome",
                    hero_ext_chrome_status: "Chrome : Disponible",
                    hero_ext_firefox_status: "Firefox : Bientôt disponible",
                    hero_trust_1: "100 liens offerts à vie",
                    hero_trust_2: "Sans engagement",
                    hero_trust_3: "Paiements Mobile Money sécurisés",

                    feat_tag: "Fonctionnalités Clés",
                    feat_title: "Conçu pour une productivité sans limite",
                    feat_subtitle: "Une suite complète d'outils intelligents pour ne plus jamais égarer une ressource critique sur le web.",
                    feat_1_title: "Résumés & Tags par IA",
                    feat_1_desc: "L'IA inspecte le contenu des articles et pages sauvegardées, rédige une synthèse claire et génère les mots-clés pertinents en 1 seconde.",
                    feat_2_title: "Scan de Santé des Liens",
                    feat_2_desc: "Fini les favoris expirés : détection proactive des erreurs 404, redirections et sites inaccessibles pour garder une base toujours saine.",
                    feat_3_title: "Google Drive Sync Cloud",
                    feat_3_desc: "Connectez votre compte Google Drive en 1 clic pour des sauvegardes automatiques d'archives ZIP compressées et sécurisées.",
                    feat_4_title: "Extension Chrome & Bientôt Firefox",
                    feat_4_desc: "Capturez n'importe quelle page web en 1 clic. Extension Chrome disponible immédiatement (compatible Brave, Edge, Arc) et arrivée imminente sur Firefox !",
                    feat_5_title: "Espaces d'Équipe & Collaboration",
                    feat_5_desc: "Partagez des dossiers de veille avec vos associés, attribuez des permissions granulaires et collaborez en temps réel.",
                    feat_6_title: "Paiement Local SasPay",
                    feat_6_desc: "Abonnez-vous facilement en FCFA via Mobile Money (Wave, MTN, Orange, Moov) ou carte bancaire sans aucune contrainte de devise.",

                    ext_tag: "Extensions Navigateurs",
                    ext_title: "Capturez le Web à la Vitesse de la Pensée",
                    ext_subtitle: "Sauvegardez n'importe quel onglet en 1 clic sans quitter votre page. Disponible immédiatement pour Google Chrome (et Chromium) et très bientôt sur Mozilla Firefox.",
                    ext_chrome_compat: "& Navigateurs Chromium",
                    ext_status_ready: "Disponible",
                    ext_chrome_feat1: "Capture instantanée en 1-clic de n'importe quel onglet",
                    ext_chrome_feat2: "Sélection du dossier et ajout de tags sans quitter la page",
                    ext_chrome_feat3: "Compatible Brave, Edge, Arc & Opera",
                    ext_chrome_feat4: "Raccourci clavier de capture ultra-rapide",
                    ext_chrome_cta: "Télécharger l'Extension Chrome (ZIP)",
                    ext_firefox_compat: "Firefox Desktop & Android",
                    ext_status_soon: "Bientôt disponible",
                    ext_firefox_feat1: "En cours de validation finale pour Firefox Add-ons (AMO)",
                    ext_firefox_feat2: "Conçu sur le standard WebExtensions moderne",
                    ext_firefox_feat3: "Respect strict de la confidentialité et zero tracking",
                    ext_firefox_feat4: "Synchronisation immédiate avec votre compte LinksVault",
                    ext_firefox_cta: "Arrive très prochainement sur Firefox",

                    step_tag: "Simplicité Absolue",
                    step_title: "Prêt en moins de 2 minutes",
                    step_1_title: "1. Capturez en 1-Clic",
                    step_1_desc: "Sauvegardez depuis l'extension Chrome, collez une URL ou importez vos favoris HTML existants.",
                    step_2_title: "2. L'IA analyse & résume",
                    step_2_desc: "Notre moteur intelligent classe le sujet, extrait les tags clés et vérifie que la page est en ligne.",
                    step_3_title: "3. Retrouvez & synchronisez",
                    step_3_desc: "Recherchez par sens ou mot-clé et conservez vos sauvegardes intactes sur Google Drive.",

                    price_tag: "Tarifs Simples & Clairs",
                    price_title: "Choisissez l'offre qui vous convient",
                    price_subtitle: "Réglez simplement par Mobile Money ou Carte Bancaire via SasPay.",
                    billing_monthly: "Facturation Mensuelle",
                    billing_yearly: "Facturation Annuelle",
                    price_per_month: "/ mois",

                    plan_free_name: "Gratuit",
                    plan_free_desc: "Idéal pour découvrir LinksVault et sauvegarder vos premiers favoris personnels.",
                    plan_free_badge: "Gratuit à vie sans carte requise",
                    feat_free_1: "100 liens",
                    feat_free_2: "10 résumés IA",
                    feat_free_3: "Sauvegarde locale & Restauration ZIP",
                    feat_free_4: "Extension Chrome & App Desktop",
                    feat_free_5: "Sauvegarde Google Drive automatique",
                    cta_choose_free: "Commencer Gratuitement",

                    plan_pro_name: "Pro",
                    plan_pro_desc: "Pour les indépendants et professionnels désireux d'une mémoire numérique illimitée enrichie par l'IA.",
                    plan_pro_badge: "Le Plus Populaire",
                    feat_pro_1: "Liens Illimités (∞)",
                    feat_pro_2: "500 résumés IA",
                    feat_pro_3: "Google Drive Sync 1-Clic",
                    feat_pro_4: "Scan de santé automatique",
                    feat_pro_5: "Jusqu'à 3 membres",
                    feat_pro_6: "Support prioritaire par email",
                    cta_choose_pro: "Essayer le Plan Pro",

                    plan_team_name: "Équipe",
                    plan_team_desc: "Collaboration avancée, rôles d'équipe, multi-comptes Cloud et quotas étendus.",
                    feat_team_1: "Liens Illimités",
                    feat_team_2: "2 000 résumés IA",
                    feat_team_3: "Jusqu'à 20 membres",
                    feat_team_4: "Multi-comptes Google Drive Cloud",
                    feat_team_5: "Scan de santé planifié & en masse",
                    feat_team_6: "Gestionnaire de compte dédié",
                    cta_choose_team: "Choisir le Plan Équipe",

                    pay_banner_title: "Paiements Locaux & Internationaux Sécurisés",
                    pay_banner_desc: "Encaissement via Mobile Money (Wave, MTN, Orange, Moov) et Cartes Bancaires propulsé par SasPay.",

                    faq_tag: "FAQ",
                    faq_title: "Questions Fréquentes",
                    faq_q1: "Puis-je payer avec mon compte Mobile Money (Wave, Orange, MTN) ?",
                    faq_a1: "Oui, absolument ! Grâce à notre passerelle partenaire SasPay, vous pouvez souscrire directement en FCFA avec Wave, MTN Mobile Money, Orange Money, Moov Money ou par carte bancaire (Visa, Mastercard).",
                    faq_q2: "Comment fonctionne la détection des liens morts ?",
                    faq_a2: "Notre scanner vérifie en arrière-plan le code retour HTTP de chacun de vos favoris (200 OK, 301 redirection, 404 introuvable, 500 erreur serveur). Vous êtes alerté dès qu'une ressource n'est plus accessible.",
                    faq_q3: "Où sont stockées les sauvegardes Google Drive ?",
                    faq_a3: "Les sauvegardes sont envoyées directement sur VOTRE propre compte Google Drive personnel dans un dossier dédié sécurisé sous forme d'archives ZIP téléchargeables à tout moment.",
                    faq_q4: "Puis-je exporter mes favoris vers un autre navigateur ?",
                    faq_a4: "Oui. Vous pouvez exporter l'intégralité de votre bibliothèque aux formats standards HTML (compatible Chrome, Safari, Firefox, Edge), JSON ou CSV en 1 clic.",
                    faq_q5: "Sur quels navigateurs l'extension fonctionne-t-elle ?",
                    faq_a5: "L'extension LinksVault est disponible immédiatement pour Google Chrome ainsi que tous les navigateurs Chromium (Brave, Microsoft Edge, Opera, Arc). L'extension officielle pour Mozilla Firefox est en cours de développement actif et arrive très prochainement !",

                    cta_final_title: "Prêt à organiser votre savoir numérique ?",
                    cta_final_desc: "Rejoignez les professionnels qui ne perdent plus jamais un lien important. Démarrez gratuitement en 30 secondes.",
                    cta_final_btn: "Créer mon espace gratuitement"
                },
                en: {
                    nav_features: "Features",
                    nav_extensions: "Extensions",
                    nav_how_it_works: "How it works",
                    nav_pricing: "Pricing",
                    nav_faq: "FAQ",
                    nav_login: "Log in",
                    nav_register: "Get Started",
                    nav_dashboard: "Dashboard",

                    hero_badge: "⚡ Subscriptions & Mobile Money Wave / Orange / MTN live",
                    hero_title_1: "Organize Your",
                    hero_title_2: "Digital Universe",
                    hero_subtitle: "The intelligent bookmark and knowledge manager powered by AI. Automatic summaries, broken link (404) scanner, 1-click Google Drive backup, and Chrome extension.",
                    hero_cta_primary: "Start For Free",
                    hero_cta_extension: "Chrome Extension",
                    hero_ext_chrome_status: "Chrome: Available",
                    hero_ext_firefox_status: "Firefox: Coming Soon",
                    hero_trust_1: "100 links free forever",
                    hero_trust_2: "Cancel anytime",
                    hero_trust_3: "Secure Mobile Money & Cards",

                    feat_tag: "Core Features",
                    feat_title: "Built for limitless productivity",
                    feat_subtitle: "A complete suite of smart tools so you never lose a critical web resource again.",
                    feat_1_title: "AI Summaries & Smart Tags",
                    feat_1_desc: "AI inspects saved articles and web pages, writes clear summaries, and generates relevant keywords in 1 second.",
                    feat_2_title: "Broken Link Health Scan",
                    feat_2_desc: "No more expired bookmarks: proactive detection of 404 errors, redirects, and dead links to keep your vault clean.",
                    feat_3_title: "Google Drive Cloud Sync",
                    feat_3_desc: "Connect your Google Drive account in 1 click for automatic, encrypted ZIP backup archives.",
                    feat_4_title: "Chrome & Soon Firefox Extension",
                    feat_4_desc: "Save your links instantly in 1 click. Chrome extension available right now (compatible with Brave, Edge, Arc) and coming soon to Mozilla Firefox.",
                    feat_5_title: "Team Workspaces & Roles",
                    feat_5_desc: "Share curated folders with your teammates, assign granular roles, and collaborate in real-time.",
                    feat_6_title: "Local SasPay Payments",
                    feat_6_desc: "Subscribe easily in FCFA via Mobile Money (Wave, MTN, Orange, Moov) or credit card with zero currency hassle.",

                    ext_tag: "Browser Extensions",
                    ext_title: "Capture the Web at the Speed of Thought",
                    ext_subtitle: "Save any browser tab in 1 click without leaving your web page. Available right now for Google Chrome (and Chromium) and very soon on Mozilla Firefox.",
                    ext_chrome_compat: "Compatible with Chrome, Brave, Edge & Opera",
                    ext_status_ready: "Available Now",
                    ext_chrome_feat1: "Instant 1-click capture from any active browser tab",
                    ext_chrome_feat2: "Select destination folder and tags without leaving the page",
                    ext_chrome_feat3: "Compatible with Brave, Edge, Arc & Opera",
                    ext_chrome_feat4: "Ultra-fast customizable keyboard shortcut",
                    ext_chrome_cta: "Download Chrome Extension (ZIP)",
                    ext_firefox_compat: "Firefox Desktop & Mobile",
                    ext_status_soon: "Coming Soon",
                    ext_firefox_feat1: "Under final packaging for Firefox Add-ons (AMO)",
                    ext_firefox_feat2: "Built upon the modern standard WebExtensions API",
                    ext_firefox_feat3: "Strict privacy guarantee with zero telemetry tracking",
                    ext_firefox_feat4: "Instant bi-directional sync with your LinksVault account",
                    ext_firefox_cta: "Coming very soon to Firefox",

                    step_tag: "Absolute Simplicity",
                    step_title: "Ready in under 2 minutes",
                    step_1_title: "1. 1-Click Capture",
                    step_1_desc: "Save from Chrome extension, paste a URL, or bulk import your existing browser HTML bookmarks.",
                    step_2_title: "2. AI analyzes & summarizes",
                    step_2_desc: "Our smart engine classifies topics, extracts key tags, and verifies link uptime.",
                    step_3_title: "3. Search & sync effortlessly",
                    step_3_desc: "Search by meaning or keyword and keep encrypted archives on your Google Drive.",

                    price_tag: "Clear & Transparent Pricing",
                    price_title: "Choose the plan that fits your needs",
                    price_subtitle: "Pay seamlessly via Mobile Money or Credit Card powered by SasPay.",
                    billing_monthly: "Monthly Billing",
                    billing_yearly: "Yearly Billing",
                    price_per_month: "/ month",

                    plan_free_name: "Free",
                    plan_free_desc: "Ideal for discovering LinksVault and storing your first personal bookmarks.",
                    plan_free_badge: "Free forever, no card needed",
                    feat_free_1: "100 links",
                    feat_free_2: "10 AI summaries",
                    feat_free_3: "Local backup & ZIP restore",
                    feat_free_4: "Chrome Extension & Desktop App",
                    feat_free_5: "Automated Google Drive sync",
                    cta_choose_free: "Get Started Free",

                    plan_pro_name: "Pro",
                    plan_pro_desc: "For freelancers and power users who want an unlimited, AI-enriched digital memory.",
                    plan_pro_badge: "Most Popular",
                    feat_pro_1: "Unlimited Links (∞)",
                    feat_pro_2: "500 AI summaries",
                    feat_pro_3: "1-Click Google Drive Sync",
                    feat_pro_4: "Automated link health scan",
                    feat_pro_5: "Up to 3 team members",
                    feat_pro_6: "Priority email support",
                    cta_choose_pro: "Start Pro Plan",

                    plan_team_name: "Team",
                    plan_team_desc: "Seamless collaboration, advanced roles, multi-cloud drives, and massive quotas.",
                    feat_team_1: "Unlimited links",
                    feat_team_2: "2,000 AI summaries",
                    feat_team_3: "Up to 20 team members",
                    feat_team_4: "Multi-account Google Drive sync",
                    feat_team_5: "Batch & scheduled health scanner",
                    feat_team_6: "Dedicated account manager",
                    cta_choose_team: "Choose Team Plan",

                    pay_banner_title: "Secure Local & International Payments",
                    pay_banner_desc: "Checkout via Mobile Money (Wave, MTN, Orange, Moov) and Cards powered by SasPay.",

                    faq_tag: "FAQ",
                    faq_title: "Frequently Asked Questions",
                    faq_q1: "Can I pay using Mobile Money (Wave, Orange, MTN)?",
                    faq_a1: "Yes, absolutely! Through our partner gateway SasPay, you can subscribe in FCFA using Wave, MTN MoMo, Orange Money, Moov Money, or credit cards (Visa, Mastercard).",
                    faq_q2: "How does broken link detection work?",
                    faq_a2: "Our scanner periodically inspects HTTP response codes for every bookmark (200 OK, 301 redirect, 404 dead link, 500 error). You are notified when a page becomes unreachable.",
                    faq_q3: "Where are Google Drive backups stored?",
                    faq_a3: "Backups are saved directly into YOUR own personal Google Drive account in a dedicated secure folder as downloadable ZIP archives.",
                    faq_q4: "Can I export my bookmarks to another browser?",
                    faq_a4: "Yes. You can export your entire library at any time to standard HTML (Chrome, Safari, Firefox, Edge compatible), JSON, or CSV in 1 click.",
                    faq_q5: "Which browsers are supported by the extension?",
                    faq_a5: "The LinksVault extension is immediately available for Google Chrome as well as all Chromium-based browsers (Brave, Microsoft Edge, Opera, Arc). The official extension for Mozilla Firefox is actively being finalized and will be launched very soon!",

                    cta_final_title: "Ready to organize your digital universe?",
                    cta_final_desc: "Join thousands of professionals who never lose an important link again. Start free in 30 seconds.",
                    cta_final_btn: "Create Free Workspace"
                }
            };

            let currentLang = localStorage.getItem('linksvault_lang') || 'fr';

            function setLanguage(lang) {
                currentLang = lang;
                localStorage.setItem('linksvault_lang', lang);
                document.documentElement.lang = lang;

                // Update text elements
                document.querySelectorAll('[data-i18n]').forEach(el => {
                    const key = el.getAttribute('data-i18n');
                    if (translations[lang] && translations[lang][key]) {
                        el.textContent = translations[lang][key];
                    }
                });

                // Update active state on language buttons
                const btnFr = document.getElementById('lang-btn-fr');
                const btnEn = document.getElementById('lang-btn-en');

                if (lang === 'fr') {
                    btnFr.className = "px-2.5 py-1 rounded-lg transition-all bg-white dark:bg-slate-700 text-slate-950 dark:text-white shadow-xs font-bold";
                    btnEn.className = "px-2.5 py-1 rounded-lg transition-all text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white";
                } else {
                    btnEn.className = "px-2.5 py-1 rounded-lg transition-all bg-white dark:bg-slate-700 text-slate-950 dark:text-white shadow-xs font-bold";
                    btnFr.className = "px-2.5 py-1 rounded-lg transition-all text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white";
                }

                // Refresh pricing sub-label according to language
                updatePricingLabels();
            }

            // -----------------------------------------------------------------
            // 3. BILLING PERIOD TOGGLE (MONTHLY / YEARLY)
            // -----------------------------------------------------------------
            let currentBilling = 'monthly';

            function setBillingPeriod(period) {
                currentBilling = period;
                const btnMonthly = document.getElementById('btn-monthly');
                const btnYearly = document.getElementById('btn-yearly');
                const pricePro = document.getElementById('price-pro');
                const priceTeam = document.getElementById('price-team');

                if (period === 'yearly') {
                    btnYearly.className = "px-5 py-2 text-xs font-bold rounded-xl transition-all bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-xs flex items-center gap-1.5";
                    btnMonthly.className = "px-5 py-2 text-xs font-bold rounded-xl transition-all text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white";

                    // 10 mois facturés (2 mois offerts)
                    // 800 * 10 / 12 = 667 FCFA / mois (ou 8 000 FCFA / an)
                    pricePro.textContent = "667 FCFA";
                    priceTeam.textContent = "1 667 FCFA";
                } else {
                    btnMonthly.className = "px-5 py-2 text-xs font-bold rounded-xl transition-all bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-xs";
                    btnYearly.className = "px-5 py-2 text-xs font-bold rounded-xl transition-all text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white flex items-center gap-1.5";

                    pricePro.textContent = "800 FCFA";
                    priceTeam.textContent = "2 000 FCFA";
                }

                updatePricingLabels();
            }

            function updatePricingLabels() {
                const subPro = document.getElementById('billing-pro-sub');
                const subTeam = document.getElementById('billing-team-sub');

                if (currentBilling === 'yearly') {
                    if (currentLang === 'fr') {
                        subPro.textContent = "Facturé 8 000 FCFA / an (2 mois offerts !)";
                        subTeam.textContent = "Facturé 20 000 FCFA / an (2 mois offerts !)";
                    } else {
                        subPro.textContent = "Billed 8,000 FCFA / yr (2 months free!)";
                        subTeam.textContent = "Billed 20,000 FCFA / yr (2 months free!)";
                    }
                } else {
                    if (currentLang === 'fr') {
                        subPro.textContent = "Sans engagement • 7 jours d'essai offerts";
                        subTeam.textContent = "Sans engagement • 14 jours d'essai offerts";
                    } else {
                        subPro.textContent = "Cancel anytime • 7-day free trial";
                        subTeam.textContent = "Cancel anytime • 14-day free trial";
                    }
                }
            }

            // Initialize on load
            document.addEventListener('DOMContentLoaded', () => {
                setLanguage(currentLang);
            });
        </script>

    </body>
</html>
