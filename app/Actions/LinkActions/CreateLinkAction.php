<?php

declare(strict_types=1);

namespace App\Actions\LinkActions;

use App\Enums\LinkVisibility;
use App\Jobs\GenerateLinkAiSummaryJob;
use App\Jobs\GenerateLinkEmbeddingJob;
use App\Models\Link;
use App\Models\Team;
use App\Models\User;
use App\Services\ContentDetectionService;
use App\Services\SubscriptionQuotaService;
use App\Services\WebPageMetadataService;
use Illuminate\Validation\ValidationException;

class CreateLinkAction
{
    public static function execute(array $data): Link
    {
        $user = auth()->user() ?? (isset($data['user_id']) ? User::find($data['user_id']) : null);
        if ($user) {
            $quotaService = app(SubscriptionQuotaService::class);
            $team = isset($data['team_id']) ? Team::find($data['team_id']) : null;
            if (! $quotaService->canCreateLink($user, $team)) {
                throw ValidationException::withMessages([
                    'url' => [__('Limite de liens atteinte pour votre plan actuel (:limit liens max). Veuillez passer au plan supérieur pour continuer à ajouter des liens.', ['limit' => $quotaService->getLinksLimit($user)])],
                ]);
            }
        }

        $generateAiSummary = ! empty($data['generate_ai_summary']);
        unset($data['generate_ai_summary'], $data['members_data']);

        $contentDetection = app(ContentDetectionService::class);
        $analysis = $contentDetection->analyze($data['url']);

        // Gérer les métadonnées existantes de manière robuste (tableau ou chaîne JSON)
        $existingMetadata = [];
        if (isset($data['metadata'])) {
            if (is_array($data['metadata'])) {
                $existingMetadata = $data['metadata'];
            } elseif (is_string($data['metadata']) && ! empty($data['metadata'])) {
                $decoded = json_decode($data['metadata'], true);
                $existingMetadata = is_array($decoded) ? $decoded : [];
            }
        }

        // Fusionner avec les métadonnées détectées
        $metadata = array_merge(
            $existingMetadata,
            $analysis['metadata'] ?? []
        );

        // Auto-génération du titre si vide
        $title = $data['title'] ?? null;
        if (empty($title)) {
            $title = $contentDetection->generateTitleFromUrl($data['url'], $analysis['type']);
        }

        // Traitement des tags si fournis sous forme de tableau
        if (isset($data['tags']) && is_array($data['tags'])) {
            $data['tags'] = implode(',', array_filter($data['tags']));
        }

        $faviconUrl = $data['favicon_url'] ?? ($metadata['favicon'] ?? (new WebPageMetadataService)->fetchFavicon($data['url']));
        $thumbnailUrl = $data['thumbnail_url'] ?? ($metadata['image'] ?? $metadata['og_image'] ?? null);

        $link = Link::create([
            ...$data,
            'title' => $title,
            'url_hash' => hash('sha256', $data['url']),
            'content_type' => $data['content_type'] ?? $analysis['type'],
            'metadata' => $metadata,
            'favicon_url' => $faviconUrl,
            'thumbnail_url' => $thumbnailUrl,
            'ai_summary_status' => $generateAiSummary ? 'pending' : 'pending',
        ]);

        // Synchronisation des membres restreints si applicable
        $vis = $link->visibility instanceof LinkVisibility
            ? $link->visibility->value
            : (string) $link->visibility;

        if ($vis === LinkVisibility::Restricted->value && ! empty($membersData)) {
            $syncData = [];
            foreach ($membersData as $item) {
                if (! empty($item['user_id'])) {
                    $syncData[] = (int) $item['user_id'];
                }
            }
            $link->members()->sync($syncData);
        }

        if ($generateAiSummary) {
            GenerateLinkAiSummaryJob::dispatch($link);
        } else {
            GenerateLinkEmbeddingJob::dispatch($link);
        }

        return $link;
    }
}
