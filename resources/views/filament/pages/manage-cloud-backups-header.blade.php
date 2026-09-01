@php
    $breadcrumbs = filament()->hasBreadcrumbs() ? $this->getBreadcrumbs() : [];
    $heading = $this->getHeading();
    $subheading = $this->getSubheading();
    $actions = $this->getCachedHeaderActions();
@endphp

<header class="fi-header flex flex-col items-start gap-4 mb-2">
    <div>
        @if ($breadcrumbs)
            <div class="mb-2">
                <x-filament::breadcrumbs :breadcrumbs="$breadcrumbs" />
            </div>
        @endif

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::PAGE_HEADER_HEADING_BEFORE, scopes: $this->getRenderHookScopes()) }}

        @if (filled($heading))
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-950 dark:text-white">
                {{ $heading }}
            </h1>
        @endif

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::PAGE_HEADER_HEADING_AFTER, scopes: $this->getRenderHookScopes()) }}

        @if (filled($subheading))
            <p class="mt-2 text-sm sm:text-base text-gray-600 dark:text-gray-400 max-w-3xl leading-relaxed">
                {{ $subheading }}
            </p>
        @endif
    </div>

    @if (filled($actions))
        <div class="flex flex-wrap items-center gap-2.5 sm:gap-3 pt-1">
            @foreach ($actions as $action)
                {{ $action }}
            @endforeach
        </div>
    @endif
</header>
