<?php

declare(strict_types=1);

namespace App\Actions\LinkActions;

use App\Enums\ContentType;
use App\Enums\LinkVisibility;
use App\Jobs\GenerateLinkAiSummaryJob;
use App\Jobs\GenerateLinkEmbeddingJob;
use App\Models\Folder;
use App\Models\Link;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use App\Services\ContentDetectionService;
use App\Services\SubscriptionQuotaService;
use App\Services\WebPageMetadataService;
use Illuminate\Support\Str;
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

        // Héritage de la catégorie du dossier si non fournie
        if (! empty($data['folder_id']) && empty($data['category_id'])) {
            $folder = Folder::find($data['folder_id']);
            if ($folder && $folder->category_id) {
                $data['category_id'] = $folder->category_id;
            }
        }

        // Traitement des tags
        $rawTags = $data['tags'] ?? null;
        $tagNames = [];
        if (is_array($rawTags)) {
            $tagNames = array_values(array_filter(array_map('trim', $rawTags)));
            $data['tags'] = implode(', ', $tagNames);
        } elseif (is_string($rawTags) && ! empty($rawTags)) {
            $tagNames = array_values(array_filter(array_map('trim', explode(',', $rawTags))));
            $data['tags'] = implode(', ', $tagNames);
        }

        $faviconUrl = $data['favicon_url'] ?? ($metadata['favicon'] ?? (new WebPageMetadataService)->fetchFavicon($data['url']));
        $thumbnailUrl = $data['thumbnail_url'] ?? ($metadata['image'] ?? $metadata['og_image'] ?? null);

        $contentType = ContentType::normalize($data['content_type'] ?? null, $analysis['type'] ?? null);

        $link = Link::create([
            ...$data,
            'title' => $title,
            'url_hash' => hash('sha256', $data['url']),
            'content_type' => $contentType,
            'metadata' => $metadata,
            'favicon_url' => $faviconUrl,
            'thumbnail_url' => $thumbnailUrl,
            'ai_summary_status' => $generateAiSummary ? 'pending' : 'pending',
        ]);

        // Synchronisation des étiquettes Tag dans la table pivot
        if (! empty($tagNames) && $user) {
            $teamId = $data['team_id'] ?? ($user->current_team_id ?? $user->personalTeam()?->id);
            $tagIds = [];
            foreach ($tagNames as $tagName) {
                $tag = Tag::firstOrCreate([
                    'user_id' => $user->id,
                    'team_id' => $teamId,
                    'slug' => Str::slug($tagName),
                ], [
                    'name' => $tagName,
                ]);
                $tagIds[] = $tag->id;
            }
            $link->tags()->sync($tagIds);
        }

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
