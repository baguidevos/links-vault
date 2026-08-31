<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\CloudBackup\VaultBackupService;
use Illuminate\Console\Command;

class BackupVaultCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vault:backup-cloud {--team= : ID de l\'équipe à sauvegarder} {--provider= : Fournisseur cible (s3, google_drive, dropbox, local)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Génère et envoie une sauvegarde complète des liens et dossiers vers les stockages cloud configurés';

    /**
     * Execute the console command.
     */
    public function handle(VaultBackupService $backupService): int
    {
        $teamId = $this->option('team');
        $provider = $this->option('provider');

        $teams = $teamId ? Team::where('id', $teamId)->get() : Team::all();

        if ($teams->isEmpty()) {
            $this->warn('Aucune équipe trouvée pour la sauvegarde.');

            return self::SUCCESS;
        }

        $this->info("Démarrage de la sauvegarde Cloud pour {$teams->count()} espace(s)...");

        foreach ($teams as $team) {
            $this->line("Sauvegarde de l'équipe : {$team->name} (ID: {$team->id})...");

            try {
                $results = $backupService->backupTeam($team, $provider);

                foreach ($results as $backup) {
                    if ($backup->status === 'completed') {
                        $this->info("  [✓] {$backup->provider_label} : {$backup->filename} ({$backup->formatted_size}, {$backup->links_count} liens)");
                    } else {
                        $this->error("  [✗] {$backup->provider_label} : Échec ({$backup->error_message})");
                    }
                }
            } catch (\Throwable $e) {
                $this->error("  [✗] Erreur : {$e->getMessage()}");
            }
        }

        $this->info('Sauvegarde Cloud terminée avec succès.');

        return self::SUCCESS;
    }
}
