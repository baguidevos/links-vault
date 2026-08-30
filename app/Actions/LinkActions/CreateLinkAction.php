<?php

declare(strict_types=1);

namespace App\Actions\LinkActions;

use App\Enums\LinkVisibility;
use App\Models\Link;
use App\Services\ContentDetectionService;

class CreateLinkAction
{
    public static function execute(array $data): Link
    {
        $membersData = $data['members_data'] ?? [];
        unset($data['members_data']);

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

        $link = Link::create([
            ...$data,
            'title' => $title,
            'url_hash' => hash('sha256', $data['url']),
            'content_type' => $data['content_type'] ?? $analysis['type'],
            'metadata' => $metadata,
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

        return $link;
    }
}
