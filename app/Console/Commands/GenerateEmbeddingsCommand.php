<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Link;
use App\Models\Team;
use App\Services\SemanticSearchService;
use Illuminate\Console\Command;

class GenerateEmbeddingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'links:generate-embeddings 
                            {--team= : ID ou Slug de l\'équipe spécifique} 
                            {--force : Forcer la régénération des embeddings existants}
                            {--limit= : Limiter le nombre de liens à traiter}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Génère les embeddings vectoriels pour la recherche sémantique des liens';

    /**
     * Execute the console command.
     */
    public function handle(SemanticSearchService $service): int
    {
        $this->info('🧠 Démarrage de l\'indexation vectorielle sémantique des liens...');

        $query = Link::query();

        if ($teamOption = $this->option('team')) {
            $team = is_numeric($teamOption)
                ? Team::find($teamOption)
                : Team::where('slug', $teamOption)->first();

            if (! $team) {
                $this->error("Équipe '{$teamOption}' introuvable.");

                return self::FAILURE;
            }

            $query->where('team_id', $team->id);
            $this->line("Filtrage sur l'équipe : {$team->name} (#{$team->id})");
        }

        if (! $this->option('force')) {
            $query->whereNull('embedding');
        }

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $links = $query->get();

        if ($links->isEmpty()) {
            $this->info('✅ Aucun lien à indexer (tous les liens ont déjà un embedding).');

            return self::SUCCESS;
        }

        $this->output->progressStart($links->count());

        $indexed = 0;
        $failed = 0;

        foreach ($links as $link) {
            $success = $service->indexLink($link, (bool) $this->option('force'));

            if ($success) {
                $indexed++;
            } else {
                $failed++;
            }

            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        $this->table(
            ['Total Liens', 'Indexés avec succès', 'Échecs / Ignorés'],
            [[$links->count(), $indexed, $failed]]
        );

        $this->info('🎉 Indexation vectorielle terminée avec succès !');

        return self::SUCCESS;
    }
}
