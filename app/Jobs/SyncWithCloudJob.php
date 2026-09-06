<?php

namespace App\Jobs;

use App\Models\SyncSetting;
use App\Models\Team;
use App\Models\User;
use App\Services\Sync\DesktopSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncWithCloudJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?User $user = null,
        public ?Team $team = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(DesktopSyncService $syncService): void
    {
        if ($this->user) {
            $syncService->sync($this->user, $this->team);

            return;
        }

        $settingsList = SyncSetting::where('auto_sync_enabled', true)->get();

        foreach ($settingsList as $settings) {
            if (! $settings->isConfigured()) {
                continue;
            }

            $user = $settings->user;
            if ($user) {
                $team = $settings->team ?? $user->teams()->first();
                $result = $syncService->sync($user, $team);
                if (! ($result['success'] ?? false)) {
                    Log::warning('Échec de synchronisation automatique pour l\'utilisateur '.$user->id.': '.($result['error'] ?? 'Inconnu'));
                }
            }
        }
    }
}
