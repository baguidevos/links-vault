@props([
    'isAdmin' => false,
])

<div class="flex items-center gap-2.5 py-1">
    <img 
        src="{{ asset('apple-touch-icon.png') }}" 
        alt="LinksVault" 
        class="w-8 h-8 rounded-lg shrink-0 object-contain shadow-xs ring-1 ring-white/10"
    />
    <div class="flex items-center gap-1.5">
        <span class="text-xl font-black tracking-tight bg-gradient-to-r from-[#0099FF] via-sky-400 to-indigo-500 dark:from-[#38bdf8] dark:via-[#60a5fa] dark:to-cyan-300 bg-clip-text text-transparent select-none">
            LinksVault
        </span>
        @if ($isAdmin)
            <span class="px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded-md bg-[#0099FF]/15 text-[#0099FF] dark:text-sky-300 border border-[#0099FF]/30">
                Admin
            </span>
        @endif
    </div>
</div>
