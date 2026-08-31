<x-filament-panels::page>
    @php
        $team = \Filament\Facades\Filament::getTenant() ?? auth()->user()?->personalTeam();
        $teamId = $team?->id ?? 0;
        $totalBackups = \App\Models\CloudBackup::where('team_id', $teamId)->where('status', 'completed')->count();
        $totalBytes = \App\Models\CloudBackup::where('team_id', $teamId)->where('status', 'completed')->sum('file_size');
        $lastBackup = \App\Models\CloudBackup::where('team_id', $teamId)->where('status', 'completed')->latest()->first();
        
        $s3Config = \App\Models\CloudStorageConfig::where('team_id', $teamId)->where('provider', 's3')->first();
        $dropboxConfig = \App\Models\CloudStorageConfig::where('team_id', $teamId)->where('provider', 'dropbox')->first();
        $gdriveModel = \App\Models\GoogleDrive::where('team_id', $teamId)->orWhere('user_id', auth()->id())->first();
        
        $formatBytes = function ($bytes) {
            if ($bytes <= 0) return '0 B';
            $units = ['B', 'KB', 'MB', 'GB', 'TB'];
            $i = (int) floor(log($bytes, 1024));
            $i = min($i, count($units) - 1);
            return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
        };
    @endphp

    {{-- Top Overview Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        {{-- Total Backups Card --}}
        <div class="p-5 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200/80 dark:border-gray-800 shadow-xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-primary-50 dark:bg-primary-950/50 flex items-center justify-center text-primary-600 dark:text-primary-400">
                <x-filament::icon icon="heroicon-o-archive-box" class="w-6 h-6" />
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Sauvegardes</p>
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $totalBackups }}</h3>
            </div>
        </div>

        {{-- Total Storage Size --}}
        <div class="p-5 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200/80 dark:border-gray-800 shadow-xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-info-50 dark:bg-info-950/50 flex items-center justify-center text-info-600 dark:text-info-400">
                <x-filament::icon icon="heroicon-o-circle-stack" class="w-6 h-6" />
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Volume Cloud</p>
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

        {{-- Active Providers Status --}}
        <div class="p-5 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200/80 dark:border-gray-800 shadow-xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-purple-50 dark:bg-purple-950/50 flex items-center justify-center text-purple-600 dark:text-purple-400">
                <x-filament::icon icon="heroicon-o-cloud" class="w-6 h-6" />
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Connecteurs</p>
                <div class="flex items-center gap-1.5 mt-1">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ ($s3Config && $s3Config->is_active) ? 'bg-success-100 text-success-800 dark:bg-success-950 dark:text-success-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }}">
                        S3/R2
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ ($dropboxConfig && $dropboxConfig->is_active) ? 'bg-success-100 text-success-800 dark:bg-success-950 dark:text-success-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }}">
                        Dropbox
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ ($gdriveModel && !empty($gdriveModel->access_token)) ? 'bg-success-100 text-success-800 dark:bg-success-950 dark:text-success-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }}">
                        GDrive
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
