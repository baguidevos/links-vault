<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\Category;
use App\Models\Folder;
use App\Models\Link;
use App\Models\SyncTombstone;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebSyncService
{
    /**
     * Traite les modifications transmises par le client Desktop (PUSH).
     *
     * @param  array{
     *     team_uuid?: string,
     *     last_synced_at?: string|null,
     *     entities: array{
     *         folders?: array<array<string, mixed>>,
     *         categories?: array<array<string, mixed>>,
     *         tags?: array<array<string, mixed>>,
     *         links?: array<array<string, mixed>>,
     *         deleted?: array<array{entity_type: string, uuid: string, deleted_at?: string}>
     *     }
     * }  $payload
     * @return array{
     *     success: bool,
     *     processed: array<string, int>,
     *     server_time: string
     * }
     */
    public function handlePush(User $user, Team $team, array $payload): array
    {
        $entities = $payload['entities'] ?? [];
        $processed = [
            'folders' => 0,
            'categories' => 0,
            'tags' => 0,
            'links' => 0,
            'deleted' => 0,
        ];

        DB::transaction(function () use ($user, $team, $entities, &$processed) {
            // 1. Synchronisation des catégories
            foreach ($entities['categories'] ?? [] as $categoryData) {
                if ($this->upsertCategory($user, $team, $categoryData)) {
                    $processed['categories']++;
                }
            }

            // 2. Synchronisation des dossiers
            foreach ($entities['folders'] ?? [] as $folderData) {
                if ($this->upsertFolder($user, $team, $folderData)) {
                    $processed['folders']++;
                }
            }

            // 3. Synchronisation des tags
            foreach ($entities['tags'] ?? [] as $tagData) {
                if ($this->upsertTag($user, $team, $tagData)) {
                    $processed['tags']++;
                }
            }

            // 4. Synchronisation des liens
            foreach ($entities['links'] ?? [] as $linkData) {
                if ($this->upsertLink($user, $team, $linkData)) {
                    $processed['links']++;
                }
            }

            // 5. Traitement des suppressions transmises par le client
            foreach ($entities['deleted'] ?? [] as $deletedItem) {
                if ($this->handleDeletedItem($team, $deletedItem)) {
                    $processed['deleted']++;
                }
            }
        });

        return [
            'success' => true,
            'processed' => $processed,
            'server_time' => now()->toIso8601String(),
        ];
    }

    /**
     * Construit le delta des entités créées ou modifiées depuis la date $since (PULL).
     *
     * @return array{
     *     server_time: string,
     *     entities: array{
     *         folders: array<int, array<string, mixed>>,
     *         categories: array<int, array<string, mixed>>,
     *         tags: array<int, array<string, mixed>>,
     *         links: array<int, array<string, mixed>>,
     *         deleted: array<int, array{entity_type: string, uuid: string, deleted_at: string}>
     *     }
     * }
     */
    public function buildPull(User $user, Team $team, ?string $since = null): array
    {
        $sinceDate = $since ? Carbon::parse($since) : null;

        // Requête des dossiers
        $foldersQuery = Folder::where('team_id', $team->id);
        if ($sinceDate) {
            $foldersQuery->where('updated_at', '>', $sinceDate);
        }
        $folders = $foldersQuery->get()->map(fn (Folder $f) => [
            'uuid' => $f->uuid,
            'name' => $f->name,
            'slug' => $f->slug,
            'color' => $f->color,
            'icon' => $f->icon,
            'description' => $f->description,
            'visibility' => $f->visibility?->value ?? $f->visibility,
            'sort_order' => $f->sort_order,
            'updated_at' => $f->updated_at?->toIso8601String(),
            'created_at' => $f->created_at?->toIso8601String(),
        ])->values()->all();

        // Requête des catégories
        $categoriesQuery = Category::where('team_id', $team->id);
        if ($sinceDate) {
            $categoriesQuery->where('updated_at', '>', $sinceDate);
        }
        $categories = $categoriesQuery->get()->map(fn (Category $c) => [
            'uuid' => $c->uuid,
            'name' => $c->name,
            'slug' => $c->slug,
            'color' => $c->color,
            'icon' => $c->icon,
            'description' => $c->description,
            'sort_order' => $c->sort_order,
            'updated_at' => $c->updated_at?->toIso8601String(),
            'created_at' => $c->created_at?->toIso8601String(),
        ])->values()->all();

        // Requête des tags
        $tagsQuery = Tag::where('team_id', $team->id);
        if ($sinceDate) {
            $tagsQuery->where('updated_at', '>', $sinceDate);
        }
        $tags = $tagsQuery->get()->map(fn (Tag $t) => [
            'uuid' => $t->uuid,
            'name' => $t->name,
            'slug' => $t->slug,
            'updated_at' => $t->updated_at?->toIso8601String(),
            'created_at' => $t->created_at?->toIso8601String(),
        ])->values()->all();

        // Requête des liens avec relations
        $linksQuery = Link::where('team_id', $team->id)->with(['folder', 'category', 'tags']);
        if ($sinceDate) {
            $linksQuery->where('updated_at', '>', $sinceDate);
        }
        $links = $linksQuery->get()->map(fn (Link $l) => [
            'uuid' => $l->uuid,
            'url' => $l->url,
            'title' => $l->title,
            'description' => $l->description,
            'objective' => $l->objective,
            'content_type' => $l->content_type?->value ?? (string) $l->content_type,
            'folder_uuid' => $l->folder?->uuid,
            'category_uuid' => $l->category?->uuid,
            'tags' => $this->extractTagNames($l),
            'is_favorite' => (bool) $l->is_favorite,
            'is_archived' => (bool) $l->is_archived,
            'ai_summary' => $l->ai_summary,
            'favicon_url' => $l->favicon_url,
            'thumbnail_url' => $l->thumbnail_url,
            'updated_at' => $l->updated_at?->toIso8601String(),
            'created_at' => $l->created_at?->toIso8601String(),
        ])->values()->all();

        // Requête des tombstones (suppressions)
        $tombstonesQuery = SyncTombstone::where('team_id', $team->id);
        if ($sinceDate) {
            $tombstonesQuery->where('deleted_at', '>', $sinceDate);
        }
        $deleted = $tombstonesQuery->get()->map(fn (SyncTombstone $st) => [
            'entity_type' => strtolower($st->entity_type),
            'uuid' => $st->entity_uuid,
            'deleted_at' => $st->deleted_at?->toIso8601String() ?? now()->toIso8601String(),
        ])->values()->all();

        return [
            'server_time' => now()->toIso8601String(),
            'entities' => [
                'folders' => $folders,
                'categories' => $categories,
                'tags' => $tags,
                'links' => $links,
                'deleted' => $deleted,
            ],
        ];
    }

    /**
     * Upsert d'une catégorie avec stratégie Last-Write-Wins.
     */
    protected function upsertCategory(User $user, Team $team, array $data): bool
    {
        $uuid = $data['uuid'] ?? null;
        if (! $uuid) {
            return false;
        }

        $incomingUpdatedAt = isset($data['updated_at']) ? Carbon::parse($data['updated_at']) : now();
        $category = Category::where('team_id', $team->id)->where('uuid', $uuid)->first();

        if ($category) {
            if ($incomingUpdatedAt->lt($category->updated_at)) {
                return false; // Version locale dépassée
            }

            $category->update([
                'name' => $data['name'] ?? $category->name,
                'slug' => $data['slug'] ?? $category->slug,
                'color' => $data['color'] ?? $category->color,
                'icon' => $data['icon'] ?? $category->icon,
                'description' => $data['description'] ?? $category->description,
                'sort_order' => $data['sort_order'] ?? $category->sort_order,
            ]);

            return true;
        }

        Category::create([
            'uuid' => $uuid,
            'team_id' => $team->id,
            'user_id' => $user->id,
            'name' => $data['name'] ?? 'Catégorie sans nom',
            'slug' => $data['slug'] ?? Str::slug($data['name'] ?? 'categorie'),
            'color' => $data['color'] ?? null,
            'icon' => $data['icon'] ?? null,
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return true;
    }

    /**
     * Upsert d'un dossier avec stratégie Last-Write-Wins.
     */
    protected function upsertFolder(User $user, Team $team, array $data): bool
    {
        $uuid = $data['uuid'] ?? null;
        if (! $uuid) {
            return false;
        }

        $incomingUpdatedAt = isset($data['updated_at']) ? Carbon::parse($data['updated_at']) : now();
        $folder = Folder::where('team_id', $team->id)->where('uuid', $uuid)->first();

        if ($folder) {
            if ($incomingUpdatedAt->lt($folder->updated_at)) {
                return false;
            }

            $folder->update([
                'name' => $data['name'] ?? $folder->name,
                'slug' => $data['slug'] ?? $folder->slug,
                'color' => $data['color'] ?? $folder->color,
                'icon' => $data['icon'] ?? $folder->icon,
                'description' => $data['description'] ?? $folder->description,
                'visibility' => $data['visibility'] ?? $folder->visibility,
                'sort_order' => $data['sort_order'] ?? $folder->sort_order,
            ]);

            return true;
        }

        Folder::create([
            'uuid' => $uuid,
            'team_id' => $team->id,
            'user_id' => $user->id,
            'name' => $data['name'] ?? 'Nouveau dossier',
            'slug' => $data['slug'] ?? Str::slug($data['name'] ?? 'dossier'),
            'color' => $data['color'] ?? null,
            'icon' => $data['icon'] ?? null,
            'description' => $data['description'] ?? null,
            'visibility' => $data['visibility'] ?? 'private',
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return true;
    }

    /**
     * Upsert d'un tag avec stratégie Last-Write-Wins.
     */
    protected function upsertTag(User $user, Team $team, array $data): bool
    {
        $uuid = $data['uuid'] ?? null;
        $name = trim((string) ($data['name'] ?? ''));

        if (empty($name)) {
            return false;
        }

        $tag = null;
        if ($uuid) {
            $tag = Tag::where('team_id', $team->id)->where('uuid', $uuid)->first();
        }

        if (! $tag) {
            $tag = Tag::where('team_id', $team->id)->where('name', $name)->first();
        }

        if ($tag) {
            $tag->update([
                'name' => $name,
                'slug' => $data['slug'] ?? Str::slug($name),
            ]);

            return true;
        }

        Tag::create([
            'uuid' => $uuid ?: (string) Str::uuid(),
            'team_id' => $team->id,
            'user_id' => $user->id,
            'name' => $name,
            'slug' => $data['slug'] ?? Str::slug($name),
        ]);

        return true;
    }

    /**
     * Upsert d'un lien avec résolution de relations et stratégie Last-Write-Wins.
     */
    protected function upsertLink(User $user, Team $team, array $data): bool
    {
        $uuid = $data['uuid'] ?? null;
        $url = trim((string) ($data['url'] ?? ''));

        if (empty($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $incomingUpdatedAt = isset($data['updated_at']) ? Carbon::parse($data['updated_at']) : now();
        $link = null;

        if ($uuid) {
            $link = Link::where('team_id', $team->id)->where('uuid', $uuid)->first();
        }

        $urlHash = hash('sha256', $url);
        if (! $link) {
            $link = Link::where('team_id', $team->id)->where('url_hash', $urlHash)->first();
        }

        // Résolution de folder_id et category_id depuis les UUIDs
        $folderId = null;
        if (! empty($data['folder_uuid'])) {
            $folderId = Folder::where('team_id', $team->id)->where('uuid', $data['folder_uuid'])->value('id');
        }

        $categoryId = null;
        if (! empty($data['category_uuid'])) {
            $categoryId = Category::where('team_id', $team->id)->where('uuid', $data['category_uuid'])->value('id');
        }

        if ($link) {
            if ($incomingUpdatedAt->lt($link->updated_at)) {
                return false;
            }

            $link->update([
                'title' => $data['title'] ?? $link->title,
                'description' => $data['description'] ?? $link->description,
                'objective' => $data['objective'] ?? $link->objective,
                'content_type' => $data['content_type'] ?? $link->content_type,
                'folder_id' => $folderId ?? $link->folder_id,
                'category_id' => $categoryId ?? $link->category_id,
                'is_favorite' => isset($data['is_favorite']) ? (bool) $data['is_favorite'] : $link->is_favorite,
                'is_archived' => isset($data['is_archived']) ? (bool) $data['is_archived'] : $link->is_archived,
                'ai_summary' => $data['ai_summary'] ?? $link->ai_summary,
                'favicon_url' => $data['favicon_url'] ?? $link->favicon_url,
                'thumbnail_url' => $data['thumbnail_url'] ?? $link->thumbnail_url,
            ]);

            $this->syncTagsForLink($team, $user, $link, $data['tags'] ?? []);

            return true;
        }

        $newLink = Link::create([
            'uuid' => $uuid ?: (string) Str::uuid(),
            'user_id' => $user->id,
            'team_id' => $team->id,
            'url' => $url,
            'url_hash' => $urlHash,
            'title' => $data['title'] ?? $url,
            'description' => $data['description'] ?? null,
            'objective' => $data['objective'] ?? null,
            'content_type' => $data['content_type'] ?? 'other',
            'folder_id' => $folderId,
            'category_id' => $categoryId,
            'is_favorite' => (bool) ($data['is_favorite'] ?? false),
            'is_archived' => (bool) ($data['is_archived'] ?? false),
            'ai_summary' => $data['ai_summary'] ?? null,
            'favicon_url' => $data['favicon_url'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
        ]);

        $this->syncTagsForLink($team, $user, $newLink, $data['tags'] ?? []);

        return true;
    }

    /**
     * Synchronise les tags associés à un lien.
     *
     * @param  array<string>  $tagNames
     */
    protected function syncTagsForLink(Team $team, User $user, Link $link, array $tagNames): void
    {
        if (empty($tagNames)) {
            return;
        }

        $tagIds = [];
        foreach ($tagNames as $name) {
            $cleanName = trim((string) $name);
            if ($cleanName === '') {
                continue;
            }

            $tag = Tag::firstOrCreate(
                ['team_id' => $team->id, 'name' => $cleanName],
                [
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'slug' => Str::slug($cleanName),
                ]
            );

            $tagIds[] = $tag->id;
        }

        $link->tags()->syncWithoutDetaching($tagIds);
    }

    /**
     * Traite un élément supprimé.
     */
    protected function handleDeletedItem(Team $team, array $item): bool
    {
        $uuid = $item['uuid'] ?? null;
        $entityType = strtolower((string) ($item['entity_type'] ?? ''));

        if (! $uuid || ! $entityType) {
            return false;
        }

        $modelClass = match ($entityType) {
            'link' => Link::class,
            'folder' => Folder::class,
            'category' => Category::class,
            'tag' => Tag::class,
            default => null,
        };

        if (! $modelClass) {
            return false;
        }

        $record = $modelClass::where('team_id', $team->id)->where('uuid', $uuid)->first();
        if ($record) {
            $record->delete();

            return true;
        }

        // Si le record n'existe déjà plus, s'assurer que le tombstone existe pour propager aux autres clients
        SyncTombstone::firstOrCreate(
            ['team_id' => $team->id, 'entity_uuid' => $uuid],
            [
                'entity_type' => class_basename($modelClass),
                'deleted_at' => isset($item['deleted_at']) ? Carbon::parse($item['deleted_at']) : now(),
            ]
        );

        return true;
    }

    /**
     * Extrait les noms des tags en évitant le masquage de la relation par la colonne tags.
     *
     * @return array<string>
     */
    protected function extractTagNames(Link $link): array
    {
        if ($link->relationLoaded('tags') && is_iterable($link->getRelation('tags'))) {
            return $link->getRelation('tags')->pluck('name')->all();
        }

        return $link->tags()->pluck('name')->all();
    }
}
