@props([
    'isAdmin' => false,
])

<div class="flex items-center gap-2.5 py-1">
    <img 
        src="{{ asset('favicon-96x96.png') }}" 
        alt="LinkVault" 
        class="w-8 h-8 rounded-lg shrink-0 object-contain shadow-xs"
    />
    <div class="flex items-center gap-1.5">
        <span class="text-xl font-black tracking-tight bg-gradient-to-r from-emerald-500 via-teal-500 to-amber-500 dark:from-emerald-400 dark:via-teal-300 dark:to-amber-300 bg-clip-text text-transparent select-none">
            LinksVault
        </span>
        @if ($isAdmin)
            <span class="px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded-md bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 border border-indigo-500/30">
                Admin
            </span>
        @endif
    </div>
</div>
