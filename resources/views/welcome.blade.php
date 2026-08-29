<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }} - LinksVault</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

        <!-- Favicons & Manifest -->
        @include('filament.render_hooks.favicons')

        <!-- Styles / Scripts -->
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="bg-void-black text-white antialiased overflow-x-hidden">

        <!-- Navigation -->
        <nav class="fixed top-0 left-0 right-0 z-50 bg-black/70 backdrop-blur-xl border-b border-white/5">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <div class="flex items-center gap-12">
                        <a href="/" class="flex items-center gap-2.5 text-xl font-bold tracking-tight">
                            <img src="{{ asset('favicon-96x96.png') }}" alt="LinksVault" class="h-7 w-7 rounded-md" />
                            <span class="bg-gradient-to-r from-emerald-400 via-teal-300 to-amber-300 bg-clip-text text-transparent">LinksVault</span>
                        </a>
                        <div class="hidden md:flex items-center gap-10">
                            <a href="#features" class="text-sm text-muted-silver hover:text-white transition-colors">Features</a>
                            <a href="#how-it-works" class="text-sm text-muted-silver hover:text-white transition-colors">How it Works</a>
                            <a href="#pricing" class="text-sm text-muted-silver hover:text-white transition-colors">Pricing</a>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="px-5 py-2.5 text-sm font-medium bg-white/10 hover:bg-white/15 rounded-full transition-all border border-white/10">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('filament.app.auth.login') }}" class="px-5 py-2.5 text-sm font-medium text-white/80 hover:text-white transition-colors">
                                Log in
                            </a>
                            @if (Route::has('filament.app.auth.register'))
                                <a href="{{ route('filament.app.auth.register') }}" class="px-5 py-2.5 text-sm font-medium bg-white text-black hover:bg-gray-100 rounded-full transition-all">
                                    Get Started
                                </a>
                            @endif
                        @endauth
                    </div>
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <section class="relative min-h-screen flex items-center pt-16">
            <!-- Background Glows -->
            <div class="absolute inset-0 overflow-hidden pointer-events-none">
                <div class="absolute top-1/4 -right-1/4 w-[800px] h-[800px] bg-framer-blue/10 rounded-full blur-[120px]"></div>
                <div class="absolute bottom-1/4 -left-1/4 w-[600px] h-[600px] bg-framer-blue/5 rounded-full blur-[100px]"></div>
            </div>

            <div class="relative max-w-7xl mx-auto px-6 lg:px-8 py-24 lg:py-32">
                <div class="grid lg:grid-cols-2 gap-16 lg:gap-24 items-center">

                    <!-- Left: Content -->
                    <div class="flex flex-col items-center lg:items-start text-center lg:text-left">
                        <!-- Badge -->
                        <div class="inline-flex items-center gap-2 px-4 py-2 bg-framer-blue/10 border border-framer-blue/20 rounded-full mb-8 animate-fade-in opacity-0">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-framer-blue opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-framer-blue"></span>
                            </span>
                            <span class="text-xs font-medium text-framer-blue tracking-wide">Now with AI Summaries</span>
                        </div>

                        <!-- Title -->
                        <h1 class="text-5xl sm:text-6xl lg:text-7xl xl:text-[88px] font-medium leading-[0.9] tracking-[-0.055em] mb-8 animate-slide-up opacity-0">
                            Organize Your<br/>
                            <span class="bg-gradient-to-r from-framer-blue to-cyan-400 bg-clip-text text-transparent">Digital Universe</span>
                        </h1>

                        <!-- Subtitle -->
                        <p class="text-lg lg:text-xl text-muted-silver leading-relaxed max-w-lg mb-10 animate-slide-up-delayed opacity-0">
                            The intelligent bookmark manager that uses AI to categorize, summarize, and make your saved links instantly searchable.
                        </p>

                        <!-- CTA Buttons -->
                        <div class="flex flex-col sm:flex-row gap-4 mb-6 animate-slide-up-delayed-2 opacity-0">
                            <a href="{{ route('filament.app.auth.register') }}" class="group inline-flex items-center justify-center gap-2 px-8 py-4 text-base font-medium bg-white text-black rounded-full hover:bg-gray-100 transition-all hover:shadow-[0_0_40px_rgba(0,153,255,0.4)]">
                                Start Free Trial
                                <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                </svg>
                            </a>
                            <a href="#features" class="inline-flex items-center justify-center px-8 py-4 text-base font-medium text-white/70 hover:text-white hover:bg-white/5 rounded-full transition-all">
                                Learn More
                            </a>
                        </div>

                        <!-- Note -->
                        <p class="text-sm text-muted-silver animate-fade-in opacity-0">No credit card required • 14-day free trial</p>
                    </div>

                    <!-- Right: Visual -->
                    <div class="relative animate-scale-in opacity-0">
                        <div class="relative rounded-2xl overflow-hidden border border-white/10 shadow-2xl shadow-framer-blue/5">
                            <!-- Window Chrome -->
                            <div class="flex items-center gap-3 px-4 py-3 bg-white/5 border-b border-white/10">
                                <div class="flex gap-2">
                                    <div class="w-3 h-3 rounded-full bg-red-500/80"></div>
                                    <div class="w-3 h-3 rounded-full bg-yellow-500/80"></div>
                                    <div class="w-3 h-3 rounded-full bg-green-500/80"></div>
                                </div>
                                <div class="flex-1 flex justify-center">
                                    <div class="px-4 py-1.5 bg-white/5 rounded-lg">
                                        <span class="text-xs text-muted-silver">linksvault.app/dashboard</span>
                                    </div>
                                </div>
                            </div>
                            <!-- Dashboard Content -->
                            <div class="flex h-72 lg:h-80">
                                <!-- Sidebar -->
                                <div class="hidden sm:flex w-14 bg-black/30 border-r border-white/5 flex-col gap-2 p-3">
                                    <div class="w-full h-8 rounded-lg bg-framer-blue/20 border border-framer-blue/30"></div>
                                    <div class="w-full h-8 rounded-lg bg-white/5"></div>
                                    <div class="w-full h-8 rounded-lg bg-white/5"></div>
                                    <div class="w-full h-px bg-white/10 my-1"></div>
                                    <div class="w-full h-8 rounded-lg bg-white/5"></div>
                                    <div class="w-full h-8 rounded-lg bg-white/5"></div>
                                </div>
                                <!-- Main Content -->
                                <div class="flex-1 p-4 space-y-3">
                                    <div class="flex items-center gap-3 p-4 rounded-xl bg-white/5 border border-white/10 hover:border-framer-blue/30 transition-colors">
                                        <span class="text-2xl">🔗</span>
                                        <div class="flex-1">
                                            <div class="h-3 w-2/3 bg-white/20 rounded mb-2"></div>
                                            <div class="h-2 w-1/3 bg-white/10 rounded"></div>
                                        </div>
                                        <div class="flex gap-1.5">
                                            <span class="w-10 h-6 bg-framer-blue/20 rounded-full"></span>
                                            <span class="w-10 h-6 bg-white/10 rounded-full"></span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 p-4 rounded-xl bg-white/5 border border-white/10 hover:border-framer-blue/30 transition-colors">
                                        <span class="text-2xl">🤖</span>
                                        <div class="flex-1">
                                            <div class="h-3 w-1/2 bg-white/20 rounded mb-2"></div>
                                            <div class="h-2 w-1/4 bg-white/10 rounded"></div>
                                        </div>
                                        <div class="flex gap-1.5">
                                            <span class="w-10 h-6 bg-framer-blue/20 rounded-full"></span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 p-4 rounded-xl bg-white/5 border border-white/10 hover:border-framer-blue/30 transition-colors">
                                        <span class="text-2xl">📊</span>
                                        <div class="flex-1">
                                            <div class="h-3 w-3/4 bg-white/20 rounded mb-2"></div>
                                            <div class="h-2 w-1/3 bg-white/10 rounded"></div>
                                        </div>
                                        <div class="flex gap-1.5">
                                            <span class="w-10 h-6 bg-framer-blue/20 rounded-full"></span>
                                            <span class="w-10 h-6 bg-white/10 rounded-full"></span>
                                            <span class="w-10 h-6 bg-white/10 rounded-full"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Glow Effect -->
                        <div class="absolute inset-0 -z-10 bg-framer-blue/20 blur-[80px] rounded-full"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Logos Section -->
        <section class="py-16 border-y border-white/5">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                <p class="text-center text-sm text-muted-silver mb-10">Trusted by teams at</p>
                <div class="flex flex-wrap justify-center items-center gap-x-16 gap-y-6">
                    <span class="text-lg font-semibold text-white/30 hover:text-white/50 transition-colors cursor-default">TechCorp</span>
                    <span class="text-lg font-semibold text-white/30 hover:text-white/50 transition-colors cursor-default">StartupXYZ</span>
                    <span class="text-lg font-semibold text-white/30 hover:text-white/50 transition-colors cursor-default">MediaFlow</span>
                    <span class="text-lg font-semibold text-white/30 hover:text-white/50 transition-colors cursor-default">DataSync</span>
                    <span class="text-lg font-semibold text-white/30 hover:text-white/50 transition-colors cursor-default">CloudNine</span>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="py-32 lg:py-40">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                <!-- Header -->
                <div class="text-center mb-20">
                    <span class="inline-block text-xs font-medium tracking-[0.2em] uppercase text-framer-blue mb-5">Features</span>
                    <h2 class="text-4xl sm:text-5xl lg:text-6xl font-medium tracking-[-0.04em] leading-[1] mb-6">
                        Everything you need to<br/>dominate your workflow
                    </h2>
                    <p class="text-lg text-muted-silver max-w-md mx-auto">
                        Powerful tools designed for modern teams who value their time.
                    </p>
                </div>

                <!-- Grid -->
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Featured Card (AI Summaries) -->
                    <div class="md:col-span-2 lg:col-span-1 lg:row-span-2 group p-8 lg:p-10 bg-near-black rounded-2xl border border-framer-blue/20 hover:border-framer-blue/40 transition-all duration-300">
                        <span class="text-4xl mb-6 block">🤖</span>
                        <h3 class="text-xl font-semibold mb-3 tracking-tight">AI-Powered Summaries</h3>
                        <p class="text-muted-silver leading-relaxed mb-8">
                            Automatically generate concise summaries of your saved links using advanced AI. Understand content at a glance.
                        </p>
                        <!-- Mini Chart Visual -->
                        <div class="flex items-end gap-2 h-20">
                            <div class="w-8 flex-1 bg-gradient-to-t from-framer-blue/30 to-framer-blue/5 rounded-t"></div>
                            <div class="w-8 flex-1 h-3/4 bg-gradient-to-t from-framer-blue/30 to-framer-blue/5 rounded-t"></div>
                            <div class="w-8 flex-1 h-1/2 bg-gradient-to-t from-framer-blue/30 to-framer-blue/5 rounded-t"></div>
                            <div class="w-8 flex-1 h-5/6 bg-gradient-to-t from-framer-blue/30 to-framer-blue/5 rounded-t"></div>
                            <div class="w-8 flex-1 h-2/3 bg-gradient-to-t from-framer-blue/30 to-framer-blue/5 rounded-t"></div>
                        </div>
                    </div>

                    <div class="p-8 bg-near-black rounded-2xl border border-white/5 hover:border-framer-blue/20 transition-all duration-300 group">
                        <span class="text-4xl mb-6 block">🏷️</span>
                        <h3 class="text-xl font-semibold mb-3 tracking-tight">Smart Tagging</h3>
                        <p class="text-muted-silver leading-relaxed">
                            Intelligent auto-tagging that categorizes links based on content analysis.
                        </p>
                    </div>

                    <div class="p-8 bg-near-black rounded-2xl border border-white/5 hover:border-framer-blue/20 transition-all duration-300 group">
                        <span class="text-4xl mb-6 block">👥</span>
                        <h3 class="text-xl font-semibold mb-3 tracking-tight">Team Collaboration</h3>
                        <p class="text-muted-silver leading-relaxed">
                            Share curated collections and build shared knowledge bases effortlessly.
                        </p>
                    </div>

                    <div class="p-8 bg-near-black rounded-2xl border border-white/5 hover:border-framer-blue/20 transition-all duration-300 group">
                        <span class="text-4xl mb-6 block">📊</span>
                        <h3 class="text-xl font-semibold mb-3 tracking-tight">YouTube Transcripts</h3>
                        <p class="text-muted-silver leading-relaxed">
                            Extract and summarize video transcripts automatically.
                        </p>
                    </div>

                    <div class="p-8 bg-near-black rounded-2xl border border-white/5 hover:border-framer-blue/20 transition-all duration-300 group">
                        <span class="text-4xl mb-6 block">☁️</span>
                        <h3 class="text-xl font-semibold mb-3 tracking-tight">Google Drive</h3>
                        <p class="text-muted-silver leading-relaxed">
                            Seamlessly connect and save documents directly to your vault.
                        </p>
                    </div>

                    <div class="p-8 bg-near-black rounded-2xl border border-white/5 hover:border-framer-blue/20 transition-all duration-300 group">
                        <span class="text-4xl mb-6 block">🔒</span>
                        <h3 class="text-xl font-semibold mb-3 tracking-tight">Secure Sharing</h3>
                        <p class="text-muted-silver leading-relaxed">
                            Trackable links with access control and engagement analytics.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section id="how-it-works" class="py-32 lg:py-40 bg-near-black">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                <div class="grid lg:grid-cols-2 gap-16 lg:gap-24 items-center">
                    <!-- Left: Steps -->
                    <div>
                        <span class="inline-block text-xs font-medium tracking-[0.2em] uppercase text-framer-blue mb-5">How it works</span>
                        <h2 class="text-4xl sm:text-5xl lg:text-6xl font-medium tracking-[-0.04em] leading-[1] mb-16">
                            Three steps to<br/>organized links
                        </h2>

                        <div class="space-y-10">
                            <div class="group flex gap-8">
                                <span class="text-5xl font-medium tracking-[-0.03em] text-framer-blue/30 group-hover:text-framer-blue/50 transition-colors">01</span>
                                <div>
                                    <h3 class="text-xl font-semibold mb-2">Save Any Link</h3>
                                    <p class="text-muted-silver leading-relaxed">
                                        Use our browser extension or paste any URL. Supports web pages, YouTube, Google Docs, and more.
                                    </p>
                                </div>
                            </div>

                            <div class="group flex gap-8">
                                <span class="text-5xl font-medium tracking-[-0.03em] text-framer-blue/30 group-hover:text-framer-blue/50 transition-colors">02</span>
                                <div>
                                    <h3 class="text-xl font-semibold mb-2">AI Does the Work</h3>
                                    <p class="text-muted-silver leading-relaxed">
                                        Auto-extract metadata, generate summaries, and tag content for instant organization.
                                    </p>
                                </div>
                            </div>

                            <div class="group flex gap-8">
                                <span class="text-5xl font-medium tracking-[-0.03em] text-framer-blue/30 group-hover:text-framer-blue/50 transition-colors">03</span>
                                <div>
                                    <h3 class="text-xl font-semibold mb-2">Search & Retrieve</h3>
                                    <p class="text-muted-silver leading-relaxed">
                                        Find any content instantly with semantic search. No more endless scrolling.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Visual -->
                    <div class="relative">
                        <div class="relative z-10 p-6 bg-void-black rounded-2xl border border-framer-blue/30 shadow-2xl">
                            <div class="mb-4">
                                <span class="inline-block px-3 py-1.5 text-xs font-semibold bg-framer-blue/15 text-framer-blue rounded-full uppercase tracking-wider">AI Summary</span>
                            </div>
                            <div class="space-y-3 mb-6">
                                <div class="h-2.5 w-full bg-white/10 rounded"></div>
                                <div class="h-2.5 w-4/5 bg-white/10 rounded"></div>
                                <div class="h-2.5 w-full bg-white/10 rounded"></div>
                                <div class="h-2.5 w-2/3 bg-white/10 rounded"></div>
                            </div>
                            <div class="flex gap-2">
                                <span class="px-3 py-1.5 text-xs bg-framer-blue/15 text-framer-blue rounded-full">research</span>
                                <span class="px-3 py-1.5 text-xs bg-white/5 text-muted-silver rounded-full">ai</span>
                            </div>
                        </div>
                        <div class="absolute inset-0 z-0 bg-framer-blue/20 blur-[80px] rounded-full"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Section -->
        <section class="py-20 lg:py-24 border-y border-white/5">
            <div class="max-w-5xl mx-auto px-6 lg:px-8">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-12">
                    <div class="text-center">
                        <div class="text-4xl lg:text-5xl font-medium tracking-[-0.03em] mb-1">
                            <span class="counter" data-target="50">0</span>M+
                        </div>
                        <div class="text-sm font-medium mb-1">Links Saved</div>
                    </div>
                    <div class="text-center">
                        <div class="text-4xl lg:text-5xl font-medium tracking-[-0.03em] mb-1">
                            <span class="counter" data-target="99">0</span>%
                        </div>
                        <div class="text-sm font-medium mb-1">Accuracy</div>
                    </div>
                    <div class="text-center">
                        <div class="text-4xl lg:text-5xl font-medium tracking-[-0.03em] mb-1">
                            <span class="counter" data-target="10">0</span>K+
                        </div>
                        <div class="text-sm font-medium mb-1">Teams</div>
                    </div>
                    <div class="text-center">
                        <div class="text-4xl lg:text-5xl font-medium tracking-[-0.03em] mb-1">
                            <span class="counter" data-target="4">0</span>.9
                        </div>
                        <div class="text-sm font-medium mb-1">Rating</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Testimonials Section -->
        <section class="py-32 lg:py-40">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                <div class="text-center mb-20">
                    <span class="inline-block text-xs font-medium tracking-[0.2em] uppercase text-framer-blue mb-5">Testimonials</span>
                    <h2 class="text-4xl sm:text-5xl lg:text-6xl font-medium tracking-[-0.04em] leading-[1]">
                        Loved by professionals<br/>worldwide
                    </h2>
                </div>

                <div class="grid md:grid-cols-3 gap-6">
                    <div class="p-8 bg-near-black rounded-2xl border border-framer-blue/20">
                        <div class="text-yellow-400 mb-5 tracking-wider">★★★★★</div>
                        <p class="text-white/90 leading-relaxed mb-6">
                            "LinksVault has completely changed how our research team works. The AI summaries alone save us hours every week."
                        </p>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-framer-blue to-cyan-400 flex items-center justify-center text-sm font-semibold">SC</div>
                            <div>
                                <div class="text-sm font-medium">Sarah Chen</div>
                                <div class="text-xs text-muted-silver">Head of Research, TechCorp</div>
                            </div>
                        </div>
                    </div>

                    <div class="p-8 bg-near-black rounded-2xl border border-white/5 hover:border-framer-blue/20 transition-all">
                        <div class="text-yellow-400 mb-5 tracking-wider">★★★★★</div>
                        <p class="text-white/90 leading-relaxed mb-6">
                            "The YouTube transcript feature is a game-changer. I can now quickly extract insights from hours of video content."
                        </p>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-framer-blue to-cyan-400 flex items-center justify-center text-sm font-semibold">MJ</div>
                            <div>
                                <div class="text-sm font-medium">Marcus Johnson</div>
                                <div class="text-xs text-muted-silver">Content Strategist, MediaFlow</div>
                            </div>
                        </div>
                    </div>

                    <div class="p-8 bg-near-black rounded-2xl border border-white/5 hover:border-framer-blue/20 transition-all">
                        <div class="text-yellow-400 mb-5 tracking-wider">★★★★★</div>
                        <p class="text-white/90 leading-relaxed mb-6">
                            "Finally, a bookmark manager that actually understands what I'm saving. The smart tagging is incredibly accurate."
                        </p>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-framer-blue to-cyan-400 flex items-center justify-center text-sm font-semibold">ER</div>
                            <div>
                                <div class="text-sm font-medium">Emily Rodriguez</div>
                                <div class="text-xs text-muted-silver">Product Manager, StartupXYZ</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing Section -->
        <section id="pricing" class="py-32 lg:py-40 bg-near-black">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                <div class="text-center mb-20">
                    <span class="inline-block text-xs font-medium tracking-[0.2em] uppercase text-framer-blue mb-5">Pricing</span>
                    <h2 class="text-4xl sm:text-5xl lg:text-6xl font-medium tracking-[-0.04em] leading-[1] mb-6">
                        Simple, transparent pricing
                    </h2>
                    <p class="text-lg text-muted-silver">
                        Start free, upgrade when you're ready.
                    </p>
                </div>

                <div class="grid md:grid-cols-3 gap-6 max-w-4xl mx-auto">
                    <!-- Starter -->
                    <div class="p-8 bg-void-black rounded-2xl border border-white/10">
                        <div class="text-lg font-semibold mb-4">Starter</div>
                        <div class="mb-2">
                            <span class="text-4xl font-medium tracking-tight">$0</span>
                            <span class="text-muted-silver">/month</span>
                        </div>
                        <p class="text-sm text-muted-silver mb-8">Perfect for personal use</p>
                        <ul class="space-y-3 mb-8">
                            <li class="flex items-center gap-3 text-sm text-muted-silver">
                                <span class="text-framer-blue">✓</span> 100 links saved
                            </li>
                            <li class="flex items-center gap-3 text-sm text-muted-silver">
                                <span class="text-framer-blue">✓</span> 5 collections
                            </li>
                            <li class="flex items-center gap-3 text-sm text-muted-silver">
                                <span class="text-framer-blue">✓</span> Basic search
                            </li>
                            <li class="flex items-center gap-3 text-sm text-muted-silver">
                                <span class="text-framer-blue">✓</span> Browser extension
                            </li>
                        </ul>
                        <a href="{{ route('filament.app.auth.register') }}" class="block text-center py-3 px-6 rounded-full bg-white/10 hover:bg-white/15 transition-all border border-white/10 font-medium text-sm">
                            Get Started
                        </a>
                    </div>

                    <!-- Pro (Featured) -->
                    <div class="relative p-8 bg-void-black rounded-2xl border-2 border-framer-blue">
                        <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-4 py-1 bg-framer-blue text-black text-xs font-semibold rounded-full uppercase tracking-wider">
                            Most Popular
                        </div>
                        <div class="text-lg font-semibold mb-4">Pro</div>
                        <div class="mb-2">
                            <span class="text-4xl font-medium tracking-tight">$12</span>
                            <span class="text-muted-silver">/month</span>
                        </div>
                        <p class="text-sm text-muted-silver mb-8">For power users</p>
                        <ul class="space-y-3 mb-8">
                            <li class="flex items-center gap-3 text-sm text-muted-silver">
                                <span class="text-framer-blue">✓</span> Unlimited links
                            </li>
                            <li class="flex items-center gap-3 text-sm text-muted-silver">
                                <span class="text-framer-blue">✓</span> Unlimited collections
                            </li>
                            <li class="flex items-center gap-3 text-sm text-muted-silver">
                                <span class="text-framer-blue">✓</span> AI summaries
                            </li>
                            <li class="flex items-center gap-3 text-sm text-muted-silver">
                                <span class="text-framer-blue">✓</span> Smart tagging
                            </li>
                            <li class="flex items-center gap-3 text-sm text-muted-silver">
                                <span class="text-framer-blue">✓</span> Team sharing
                            </li>
                        </ul>
                        <a href="{{ route('filament.app.auth.register') }}" class="block text-center py-3 px-6 rounded-full bg-framer-blue hover:bg-framer-blue/90 transition-all font-medium text-sm text-black">
                            Start Free Trial
                        </a>
                    </div>

                    <!-- Team -->
                    <div class="p-8 bg-void-black rounded-2xl border border-white/10">
                        <div class="text-lg font-semibold mb-4">Team</div>
                        <div class="mb-2">
                            <span class="text-4xl font-medium tracking-tight">$29</span>
                            <span class="text-muted-silver">/user/mo</span>
                        </div>
                        <p class="text-sm text-muted-silver mb-8">For growing teams</p>
                        <ul class="space-y-3 mb-8">
                            <li class="flex items-center gap-3 text-sm text-muted-silver">
                                <span class="text-framer-blue">✓</span> Everything in Pro
                            </li>
                            <li class="flex items-center gap-3 text-sm text-muted-silver">
                                <span class="text-framer-blue">✓</span> 5 team members
                            </li>
                            <li class="flex items-center gap-3 text-sm text-muted-silver">
                                <span class="text-framer-blue">✓</span> Admin dashboard
                            </li>
                            <li class="flex items-center gap-3 text-sm text-muted-silver">
                                <span class="text-framer-blue">✓</span> Priority support
                            </li>
                            <li class="flex items-center gap-3 text-sm text-muted-silver">
                                <span class="text-framer-blue">✓</span> Analytics
                            </li>
                        </ul>
                        <a href="{{ route('filament.app.auth.register') }}" class="block text-center py-3 px-6 rounded-full bg-white/10 hover:bg-white/15 transition-all border border-white/10 font-medium text-sm">
                            Contact Sales
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="relative py-32 lg:py-40 overflow-hidden">
            <!-- Background Glows -->
            <div class="absolute inset-0 overflow-hidden pointer-events-none">
                <div class="absolute top-0 left-1/4 w-[600px] h-[600px] bg-framer-blue/15 rounded-full blur-[120px]"></div>
                <div class="absolute bottom-0 right-1/4 w-[500px] h-[500px] bg-framer-blue/10 rounded-full blur-[100px]"></div>
            </div>

            <div class="relative max-w-3xl mx-auto px-6 lg:px-8 text-center">
                <h2 class="text-4xl sm:text-5xl lg:text-6xl font-medium tracking-[-0.04em] leading-[1] mb-6">
                    Ready to transform<br/>your workflow?
                </h2>
                <p class="text-lg text-muted-silver mb-10">
                    Join thousands of professionals who've already made the switch.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center mb-6">
                    <a href="{{ route('filament.app.auth.register') }}" class="group inline-flex items-center justify-center gap-2 px-8 py-4 text-base font-medium bg-white text-black rounded-full hover:bg-gray-100 transition-all hover:shadow-[0_0_40px_rgba(0,153,255,0.4)]">
                        Start Free Trial
                        <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </a>
                    <a href="#" class="inline-flex items-center justify-center px-8 py-4 text-base font-medium text-white/70 hover:text-white hover:bg-white/5 rounded-full transition-all">
                        Schedule Demo
                    </a>
                </div>
                <p class="text-sm text-muted-silver">No credit card required • 14-day free trial • Cancel anytime</p>
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-20 border-t border-white/5">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                <div class="grid md:grid-cols-5 gap-12 mb-16">
                    <div class="md:col-span-2">
                        <a href="/" class="text-xl font-semibold tracking-tight">LinksVault</a>
                        <p class="mt-4 text-sm text-muted-silver max-w-xs">
                            The intelligent bookmark manager for modern teams.
                        </p>
                    </div>
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wider mb-4">Product</h4>
                        <ul class="space-y-3">
                            <li><a href="#features" class="text-sm text-muted-silver hover:text-white transition-colors">Features</a></li>
                            <li><a href="#pricing" class="text-sm text-muted-silver hover:text-white transition-colors">Pricing</a></li>
                            <li><a href="#" class="text-sm text-muted-silver hover:text-white transition-colors">Documentation</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wider mb-4">Company</h4>
                        <ul class="space-y-3">
                            <li><a href="#" class="text-sm text-muted-silver hover:text-white transition-colors">About</a></li>
                            <li><a href="#" class="text-sm text-muted-silver hover:text-white transition-colors">Blog</a></li>
                            <li><a href="#" class="text-sm text-muted-silver hover:text-white transition-colors">Contact</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wider mb-4">Legal</h4>
                        <ul class="space-y-3">
                            <li><a href="#" class="text-sm text-muted-silver hover:text-white transition-colors">Privacy</a></li>
                            <li><a href="#" class="text-sm text-muted-silver hover:text-white transition-colors">Terms</a></li>
                        </ul>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row justify-between items-center pt-8 border-t border-white/5">
                    <p class="text-sm text-muted-silver">© {{ date('Y') }} LinksVault. All rights reserved.</p>
                    <div class="flex gap-6 mt-4 sm:mt-0">
                        <a href="#" class="text-sm text-muted-silver hover:text-white transition-colors">𝕏</a>
                        <a href="#" class="text-sm text-muted-silver hover:text-white transition-colors">GH</a>
                        <a href="#" class="text-sm text-muted-silver hover:text-white transition-colors">in</a>
                    </div>
                </div>
            </div>
        </footer>

        <!-- Scroll Animations -->
        <script>
            // Intersection Observer for scroll animations
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -80px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');

                        // Counter animation
                        if (entry.target.querySelector('.counter')) {
                            entry.target.querySelectorAll('.counter').forEach(counter => {
                                animateCounter(counter);
                            });
                        }
                    }
                });
            }, observerOptions);

            function animateCounter(element) {
                const target = parseInt(element.dataset.target);
                const duration = 2000;
                const startTime = performance.now();

                function update(currentTime) {
                    const elapsed = currentTime - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    const easeOut = 1 - Math.pow(1 - progress, 3);
                    element.textContent = Math.floor(target * easeOut);
                    if (progress < 1) requestAnimationFrame(update);
                }
                requestAnimationFrame(update);
            }

            // Observe elements
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('.animate-fade-in, .animate-slide-up, .animate-slide-up-delayed, .animate-slide-up-delayed-2, .animate-scale-in').forEach(el => {
                    observer.observe(el);
                });
            });

            // Smooth scroll
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            });

            // Nav background on scroll
            const nav = document.querySelector('nav');
            window.addEventListener('scroll', () => {
                nav.style.background = window.scrollY > 50 ? 'rgba(0, 0, 0, 0.95)' : 'rgba(0, 0, 0, 0.7)';
            });
        </script>
    </body>
</html>
