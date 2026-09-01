<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Link;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;
use Throwable;

class SemanticSearchService
{
    /**
     * Générer le vecteur d'embedding pour une chaîne de texte donnée.
     *
     * @return array<float>
     */
    public function generateEmbedding(string $text): array
    {
        $cleanText = trim(strip_tags($text));

        if (empty($cleanText)) {
            return [];
        }

        try {
            $response = Embeddings::for([$cleanText])->generate();

            return $response->first() ?? [];
        } catch (Throwable $e) {
            Log::warning("Échec de génération d'embedding IA : ".$e->getMessage());

            return [];
        }
    }

    /**
     * Indexer vectoriellement un lien (génère et sauvegarde l'embedding).
     */
    public function indexLink(Link $link, bool $force = false): bool
    {
        if (! $force && $link->hasEmbedding()) {
            return true;
        }

        $textToIndex = $link->getEmbeddingText();

        if (empty(trim($textToIndex))) {
            return false;
        }

        $vector = $this->generateEmbedding($textToIndex);

        if (empty($vector)) {
            return false;
        }

        $link->update([
            'embedding' => $vector,
            'embedding_model' => config('ai.default_for_embeddings', 'openai'),
            'embedding_generated_at' => now(),
        ]);

        return true;
    }

    /**
     * Calculer la similarité cosinus entre deux vecteurs.
     *
     * @param  array<float>  $vecA
     * @param  array<float>  $vecB
     */
    public function cosineSimilarity(array $vecA, array $vecB): float
    {
        $count = count($vecA);

        if ($count === 0 || $count !== count($vecB)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $valA = (float) $vecA[$i];
            $valB = (float) $vecB[$i];

            $dotProduct += $valA * $valB;
            $normA += $valA * $valA;
            $normB += $valB * $valB;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        $similarity = $dotProduct / (sqrt($normA) * sqrt($normB));

        // Normaliser entre 0.0 et 1.0 (ou borner entre -1 et 1)
        return max(-1.0, min(1.0, $similarity));
    }

    /**
     * Rechercher les liens d'un tenant/espace par similarité sémantique.
     *
     * @return Collection<int, Link>
     */
    public function search(
        string $query,
        ?Model $team = null,
        int $limit = 10,
        float $minSimilarity = 0.20
    ): Collection {
        $cleanQuery = trim($query);

        if (empty($cleanQuery)) {
            return collect();
        }

        // 1. Générer l'embedding de la requête
        $queryVector = $this->generateEmbedding($cleanQuery);

        if (empty($queryVector)) {
            return collect();
        }

        // 2. Récupérer les liens avec embeddings du périmètre
        $queryBuilder = Link::query()
            ->with(['folder', 'category'])
            ->whereNotNull('embedding')
            ->where('is_archived', false);

        if ($team) {
            $queryBuilder->where('team_id', $team->getKey());
        }

        $links = $queryBuilder->get();

        if ($links->isEmpty()) {
            return collect();
        }

        // 3. Calculer le score de similarité pour chaque lien
        $scoredLinks = $links->map(function (Link $link) use ($queryVector) {
            $linkVector = $link->embedding;

            if (! is_array($linkVector) || empty($linkVector)) {
                $link->similarity_score = 0.0;
                $link->similarity_percentage = 0;

                return $link;
            }

            $score = $this->cosineSimilarity($queryVector, $linkVector);
            $link->similarity_score = $score;
            // Pourcentage lisible (converti de l'intervalle [0, 1])
            $link->similarity_percentage = (int) round(max(0.0, $score) * 100);

            return $link;
        });

        // 4. Filtrer par similarité minimale et trier par pertinence décroissante
        return $scoredLinks
            ->filter(fn (Link $link) => ($link->similarity_score ?? 0) >= $minSimilarity)
            ->sortByDesc('similarity_score')
            ->take($limit)
            ->values();
    }

    /**
     * Indexer l'ensemble des liens d'une équipe.
     *
     * @return array{indexed: int, skipped: int, errors: int}
     */
    public function indexTeamLinks(Model $team, bool $force = false): array
    {
        $links = Link::where('team_id', $team->getKey())->get();
        $indexed = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($links as $link) {
            if (! $force && $link->hasEmbedding()) {
                $skipped++;

                continue;
            }

            if ($this->indexLink($link, $force)) {
                $indexed++;
            } else {
                $errors++;
            }
        }

        return [
            'indexed' => $indexed,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }
}
