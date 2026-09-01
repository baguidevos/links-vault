<x-filament-panels::page>
    @php
        $team = \Filament\Facades\Filament::getTenant() ?? auth()->user()?->personalTeam();
        $teamId = $team?->id ?? 0;
        $totalBackups = \App\Models\CloudBackup::where('team_id', $teamId)->where('status', 'completed')->count();
        $totalBytes = \App\Models\CloudBackup::where('team_id', $teamId)->where('status', 'completed')->sum('file_size');
        $lastBackup = \App\Models\CloudBackup::where('team_id', $teamId)->where('status', 'completed')->latest()->first();
        
        $gdriveConfig = \App\Models\CloudStorageConfig::where('team_id', $teamId)->where('provider', 'google_drive')->first();
        $localConfig = \App\Models\CloudStorageConfig::where('team_id', $teamId)->where('provider', 'local')->first();
        $gdriveModel = \App\Models\GoogleDrive::where('team_id', $teamId)->orWhere('user_id', auth()->id())->first();
        $hasGdriveToken = !empty($gdriveModel?->access_token) || !empty($gdriveConfig?->credentials['refresh_token'] ?? null);
        
        $formatBytes = function ($bytes) {
            if ($bytes <= 0) return '0 B';
            $units = ['B', 'KB', 'MB', 'GB', 'TB'];
            $i = (int) floor(log($bytes, 1024));
            $i = min($i, count($units) - 1);
            return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
        };
    @endphp

    {{-- Top Overview Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {{-- Total Backups Card --}}
        <div class="p-5 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200/80 dark:border-gray-800 shadow-xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-primary-50 dark:bg-primary-950/50 flex items-center justify-center text-primary-600 dark:text-primary-400">
                <x-filament::icon icon="heroicon-o-archive-box" class="w-6 h-6" />
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Sauvegardes</p>
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $totalBackups }}</h3>
            </div>
        </div>

        {{-- Total Storage Size --}}
        <div class="p-5 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200/80 dark:border-gray-800 shadow-xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-info-50 dark:bg-info-950/50 flex items-center justify-center text-info-600 dark:text-info-400">
                <x-filament::icon icon="heroicon-o-circle-stack" class="w-6 h-6" />
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Volume Total</p>
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $formatBytes($totalBytes) }}</h3>
            </div>
        </div>

        {{-- Last Backup Time --}}
        <div class="p-5 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200/80 dark:border-gray-800 shadow-xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-success-50 dark:bg-success-950/50 flex items-center justify-center text-success-600 dark:text-success-400">
                <x-filament::icon icon="heroicon-o-check-badge" class="w-6 h-6" />
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Dernière Sauvegarde</p>
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                    {{ $lastBackup ? $lastBackup->created_at->diffForHumans() : 'Aucune' }}
                </h3>
            </div>
        </div>

        {{-- Active Providers Status (Google Drive & Local) --}}
        <div class="p-5 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200/80 dark:border-gray-800 shadow-xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-amber-50 dark:bg-amber-950/50 flex items-center justify-center text-amber-600 dark:text-amber-400">
                <x-filament::icon icon="heroicon-o-cloud" class="w-6 h-6" />
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Destinations Actives</p>
                <div class="flex items-center gap-1.5 mt-1.5">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium {{ $hasGdriveToken ? 'bg-success-100 text-success-800 dark:bg-success-950 dark:text-success-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $hasGdriveToken ? 'bg-success-500 animate-pulse' : 'bg-gray-400' }}"></span>
                        Google Drive {{ $gdriveModel?->email ? "({$gdriveModel->email})" : ($hasGdriveToken ? '(Connecté)' : '(Non lié)') }}
                    </span>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium {{ ($localConfig?->is_active ?? true) ? 'bg-success-100 text-success-800 dark:bg-success-950 dark:text-success-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ ($localConfig?->is_active ?? true) ? 'bg-success-500' : 'bg-gray-400' }}"></span>
                        Stockage Local
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Backups Table --}}
    <div class="bg-white dark:bg-gray-900 border border-gray-200/80 dark:border-gray-800 rounded-2xl overflow-hidden shadow-xs">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
