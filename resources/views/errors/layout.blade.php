<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Erreur') — LinksVault</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96">

    <style>
        :root {
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.25);
            --bg-base: #090d16;
            --card-bg: rgba(15, 23, 42, 0.75);
            --card-border: rgba(255, 255, 255, 0.1);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        @media (prefers-color-scheme: light) {
            :root {
                --primary: #4f46e5;
                --primary-glow: rgba(79, 70, 229, 0.15);
                --bg-base: #f8fafc;
                --card-bg: rgba(255, 255, 255, 0.9);
                --card-border: rgba(0, 0, 0, 0.08);
                --text-main: #0f172a;
                --text-muted: #64748b;
            }
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-base);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            position: relative;
            overflow-x: hidden;
        }

        /* Ambient Glow Background */
        .ambient-glow {
            position: absolute;
            border-radius: 50%;
            filter: blur(120px);
            pointer-events: none;
            z-index: 0;
        }

        .glow-1 {
            top: 10%;
            left: 20%;
            width: 450px;
            height: 450px;
            background: var(--glow-color, rgba(99, 102, 241, 0.18));
        }

        .glow-2 {
            bottom: 10%;
            right: 20%;
            width: 380px;
            height: 380px;
            background: var(--glow-secondary, rgba(236, 72, 153, 0.12));
        }

        /* Grid pattern overlay */
        .bg-grid {
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(255, 255, 255, 0.07) 1px, transparent 1px);
            background-size: 32px 32px;
            pointer-events: none;
            z-index: 0;
        }

        .card-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 580px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 28px;
            padding: 3rem 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            text-align: center;
            animation: floatUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes floatUp {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .brand-logo {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 2rem;
            text-decoration: none;
        }

        .brand-logo img {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            box-shadow: 0 8px 16px -4px var(--primary-glow);
        }

        .brand-name {
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            background: linear-gradient(135deg, #a5b4fc 0%, #6366f1 50%, #ec4899 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .badge-code {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 1rem;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 1.5rem;
            background: var(--badge-bg, rgba(99, 102, 241, 0.15));
            color: var(--badge-text, #818cf8);
            border: 1px solid var(--badge-border, rgba(99, 102, 241, 0.3));
        }

        .error-title {
            font-size: 2.15rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.2;
            margin-bottom: 1rem;
            color: var(--text-main);
        }

        .error-desc {
            font-size: 1rem;
            line-height: 1.6;
            color: var(--text-muted);
            margin-bottom: 2.25rem;
            font-weight: 400;
        }

        .actions-group {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 0.85rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.85rem 1.5rem;
            border-radius: 14px;
            font-size: 0.925rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: #ffffff;
            box-shadow: 0 10px 20px -5px rgba(99, 102, 241, 0.4);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
            transform: translateY(-2px);
            box-shadow: 0 14px 24px -5px rgba(99, 102, 241, 0.5);
            color: #ffffff;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.06);
            color: var(--text-main);
            border: 1px solid var(--card-border);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.12);
            transform: translateY(-2px);
            color: var(--text-main);
        }

        .footer-note {
            margin-top: 2.5rem;
            font-size: 0.8rem;
            color: var(--text-muted);
            opacity: 0.7;
        }

        .illustration-icon {
            margin: 0 auto 1.5rem auto;
            width: 84px;
            height: 84px;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--icon-bg, rgba(99, 102, 241, 0.12));
            color: var(--icon-color, #818cf8);
            border: 1px solid var(--icon-border, rgba(99, 102, 241, 0.25));
            box-shadow: 0 12px 28px -6px var(--primary-glow);
        }

        .illustration-icon svg {
            width: 44px;
            height: 44px;
        }
    </style>
</head>
<body>
    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>
    <div class="bg-grid"></div>

    <main class="card-container">
        <!-- Logo -->
        <a href="{{ url('/app') }}" class="brand-logo">
            <img src="{{ asset('favicon-96x96.png') }}" alt="LinksVault">
            <span class="brand-name">LinksVault</span>
        </a>

        <!-- Icon -->
        <div class="illustration-icon">
            @yield('icon')
        </div>

        <!-- Badge Code -->
        <div>
            <span class="badge-code">
                @yield('badge', 'Erreur ' . ($exception?->getStatusCode() ?? 500))
            </span>
        </div>

        <!-- Title -->
        <h1 class="error-title">
            @yield('heading', 'Une erreur est survenue')
        </h1>

        <!-- Description -->
        <p class="error-desc">
            @yield('description', 'La page demandée est temporairement inaccessible.')
        </p>

        <!-- Actions -->
        <div class="actions-group">
            <a href="{{ url('/app') }}" class="btn btn-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                <span>Accéder au Coffre-fort</span>
            </a>

            <button onclick="window.history.length > 1 ? window.history.back() : window.location.reload();" class="btn btn-secondary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 14 4 9l5-5"/>
                    <path d="M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5v0a5.5 5.5 0 0 1-5.5 5.5H11"/>
                </svg>
                <span>Page précédente</span>
            </button>
        </div>

        <div class="footer-note">
            LinksVault • Gestionnaire intelligent de liens & connaissances
        </div>
    </main>
</body>
</html>
