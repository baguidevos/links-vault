<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\LinkHealthStatus;
use App\Models\Link;
use App\Models\Team;
use App\Services\LinkHealthService;
use Illuminate\Console\Command;

class CheckLinksHealthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'links:check-health 
                            {--team= : ID de l\'espace de travail (Team)} 
                            {--limit=100 : Nombre maximum de liens à vérifier} 
                            {--all : Vérifier tous les liens de la base} 
                            {--force : Forcer la réévaluation même si récemment vérifiés}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Vérifier la disponibilité HTTP et détecter les liens morts ou redirections';

    /**
     * Execute the console command.
     */
    public function handle(LinkHealthService $healthService): int
    {
        $teamId = $this->option('team');
        $limit = (int) $this->option('limit');
        $force = (bool) $this->option('force');
        $all = (bool) $this->option('all');

        $this->info('🩺 Démarrage du scan de santé des liens...');

        $query = Link::query();

        if ($teamId) {
            $team = Team::find($teamId);
            if (! $team) {
                $this->error("Espace de travail avec l'ID {$teamId} introuvable.");

                return self::FAILURE;
            }
            $query->where('team_id', $team->id);
            $this->line("Filtrage sur l'espace : {$team->name}");
        } elseif (! $all) {
            $this->line('Analyse des liens non vérifiés ou anciens (> 7 jours)...');
        }

        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('last_health_checked_at')
                    ->orWhere('health_status', 'unknown')
                    ->orWhere('last_health_checked_at', '<', now()->subDays(7));
            });
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $links = $query->get();
        $total = $links->count();

        if ($total === 0) {
            $this->info('✅ Aucun lien à vérifier. Tous les liens sont à jour !');

            return self::SUCCESS;
        }

        $this->line("Analyse de {$total} lien(s) en cours...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $stats = [
            'healthy' => 0,
            'broken' => 0,
            'redirect' => 0,
        ];

        foreach ($links as $link) {
            $res = $healthService->checkLink($link);
            if ($res['health_status'] === LinkHealthStatus::Healthy) {
                $stats['healthy']++;
            } elseif ($res['health_status'] === LinkHealthStatus::Broken) {
                $stats['broken']++;
            } elseif ($res['health_status'] === LinkHealthStatus::Redirect) {
                $stats['redirect']++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Métrique', 'Nombre'],
            [
                ['Total vérifiés', $total],
                ['🟢 En ligne (200 OK)', $stats['healthy']],
                ['🟡 Redirections (301/302)', $stats['redirect']],
                ['🔴 Liens morts / Inaccessibles', $stats['broken']],
            ]
        );

        $this->info('🎉 Scan de santé terminé avec succès !');

        return self::SUCCESS;
    }
}
