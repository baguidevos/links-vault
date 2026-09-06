<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\Category;
use App\Models\Folder;
use App\Models\Link;
use App\Models\SyncSetting;
use App\Models\SyncTombstone;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class DesktopSyncService
{
    /**
     * Exécute un cycle complet de synchronisation bidirectionnelle (Push puis Pull).
     *
     * @return array{success: bool, message?: string, error?: string, stats?: array<string, mixed>}
     */
    public function sync(User $user, Model|Team|null $team = null): array
    {
        if ($team && ! ($team instanceof Team)) {
            $team = Team::find($team->id);
        }

        $settings = SyncSetting::where('user_id', $user->id)->first();

        if (! $settings || ! $settings->isConfigured()) {
            return [
                'success' => false,
                'error' => 'La synchronisation n\'est pas configurée. Renseignez l\'URL et le token dans les paramètres.',
            ];
        }

        $team = $team ?? $this->resolveTeam($user, $settings);
        if (! $team) {
            return [
                'success' => false,
                'error' => 'Aucune équipe locale associée pour la synchronisation.',
            ];
        }

        $settings->update([
            'sync_status' => 'syncing',
            'last_error' => null,
        ]);

        try {
            $serverUrl = rtrim($settings->server_url, '/');
            $apiToken = $settings->api_token;
            $lastSyncedAt = $settings->last_synced_at;
            $lastSyncedAtIso = $lastSyncedAt?->toIso8601String();

            // 1. PUSH : Collecter et envoyer les modifications locales vers le Web
            $pushPayload = $this->buildPushPayload($team, $lastSyncedAt);

            $pushResponse = Http::withToken($apiToken)
                ->acceptJson()
                ->timeout(20)
                ->post("{$serverUrl}/api/v1/sync/push", $pushPayload);

            if (! $pushResponse->successful()) {
                throw new \RuntimeException('Échec de l\'envoi (Push) : '.$pushResponse->body());
            }

            // 2. PULL : Récupérer les modifications depuis le Web
            $pullUrl = "{$serverUrl}/api/v1/sync/pull";
            $pullParams = [
                'team_uuid' => $team->uuid,
            ];
            if ($lastSyncedAtIso) {
                $pullParams['since'] = $lastSyncedAtIso;
            }

            $pullResponse = Http::withToken($apiToken)
                ->acceptJson()
                ->timeout(20)
                ->get($pullUrl, $pullParams);

            if (! $pullResponse->successful()) {
                throw new \RuntimeException('Échec de la récupération (Pull) : '.$pullResponse->body());
            }

            $pullData = $pullResponse->json();
            $serverTime = $pullData['server_time'] ?? now()->toIso8601String();

            // 3. Appliquer les données reçues du Web dans la base SQLite locale
            $appliedStats = $this->applyPullData($user, $team, $pullData['entities'] ?? []);

            // 4. Mettre à jour l'état de synchronisation
            $settings->update([
                'last_synced_at' => Carbon::parse($serverTime),
                'sync_status' => 'idle',
                'last_error' => null,
            ]);

            return [
                'success' => true,
                'message' => 'Synchronisation réussie.',
                'stats' => $appliedStats,
            ];
        } catch (Throwable $e) {
            Log::error('Erreur DesktopSyncService: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            $settings->update([
                'sync_status' => 'error',
                'last_error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Prépare le payload Push contenant les deltas locaux.
     *
     * @return array<string, mixed>
     */
    protected function buildPushPayload(Team $team, ?Carbon $lastSyncedAt): array
    {
        $foldersQuery = Folder::where('team_id', $team->id);
        $categoriesQuery = Category::where('team_id', $team->id);
        $tagsQuery = Tag::where('team_id', $team->id);
        $linksQuery = Link::where('team_id', $team->id)->with(['folder', 'category', 'tags']);
        $tombstonesQuery = SyncTombstone::where('team_id', $team->id);

        if ($lastSyncedAt) {
            $foldersQuery->where('updated_at', '>', $lastSyncedAt);
            $categoriesQuery->where('updated_at', '>', $lastSyncedAt);
            $tagsQuery->where('updated_at', '>', $lastSyncedAt);
            $linksQuery->where('updated_at', '>', $lastSyncedAt);
            $tombstonesQuery->where('deleted_at', '>', $lastSyncedAt);
        }

        return [
            'team_uuid' => $team->uuid,
            'last_synced_at' => $lastSyncedAt?->toIso8601String(),
            'entities' => [
                'folders' => $foldersQuery->get()->map(fn (Folder $f) => [
                    'uuid' => $f->uuid,
                    'name' => $f->name,
                    'slug' => $f->slug,
                    'color' => $f->color,
                    'icon' => $f->icon,
                    'description' => $f->description,
                    'visibility' => $f->visibility?->value ?? $f->visibility,
                    'sort_order' => $f->sort_order,
                    'updated_at' => $f->updated_at?->toIso8601String(),
                ])->values()->all(),

                'categories' => $categoriesQuery->get()->map(fn (Category $c) => [
                    'uuid' => $c->uuid,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'color' => $c->color,
                    'icon' => $c->icon,
                    'description' => $c->description,
                    'sort_order' => $c->sort_order,
                    'updated_at' => $c->updated_at?->toIso8601String(),
                ])->values()->all(),

                'tags' => $tagsQuery->get()->map(fn (Tag $t) => [
                    'uuid' => $t->uuid,
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'updated_at' => $t->updated_at?->toIso8601String(),
                ])->values()->all(),

                'links' => $linksQuery->get()->map(fn (Link $l) => [
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
                ])->values()->all(),

                'deleted' => $tombstonesQuery->get()->map(fn (SyncTombstone $st) => [
                    'entity_type' => strtolower($st->entity_type),
                    'uuid' => $st->entity_uuid,
                    'deleted_at' => $st->deleted_at?->toIso8601String(),
                ])->values()->all(),
            ],
        ];
    }

    /**
     * Applique les modifications distantes reçues lors du Pull dans SQLite local.
     *
     * @param  array<string, mixed>  $entities
     * @return array<string, int>
     */
    protected function applyPullData(User $user, Team $team, array $entities): array
    {
        $stats = [
            'folders' => 0,
            'categories' => 0,
            'tags' => 0,
            'links' => 0,
            'deleted' => 0,
        ];

        DB::transaction(function () use ($user, $team, $entities, &$stats) {
            // Catégories
            foreach ($entities['categories'] ?? [] as $categoryData) {
                if ($this->upsertLocalCategory($user, $team, $categoryData)) {
                    $stats['categories']++;
                }
            }

            // Dossiers
            foreach ($entities['folders'] ?? [] as $folderData) {
                if ($this->upsertLocalFolder($user, $team, $folderData)) {
                    $stats['folders']++;
                }
            }

            // Tags
            foreach ($entities['tags'] ?? [] as $tagData) {
                if ($this->upsertLocalTag($user, $team, $tagData)) {
                    $stats['tags']++;
                }
            }

            // Liens
            foreach ($entities['links'] ?? [] as $linkData) {
                if ($this->upsertLocalLink($user, $team, $linkData)) {
                    $stats['links']++;
                }
            }

            // Suppressions
            foreach ($entities['deleted'] ?? [] as $deletedItem) {
                if ($this->deleteLocalItem($team, $deletedItem)) {
                    $stats['deleted']++;
                }
            }
        });

        return $stats;
    }

    protected function upsertLocalCategory(User $user, Team $team, array $data): bool
    {
        $uuid = $data['uuid'] ?? null;
        if (! $uuid) {
            return false;
        }

        $incomingUpdatedAt = isset($data['updated_at']) ? Carbon::parse($data['updated_at']) : now();
        $category = Category::where('team_id', $team->id)->where('uuid', $uuid)->first();

        if ($category) {
            if ($incomingUpdatedAt->lt($category->updated_at)) {
                return false;
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
            'name' => $data['name'] ?? 'Catégorie',
            'slug' => $data['slug'] ?? Str::slug($data['name'] ?? 'cat'),
            'color' => $data['color'] ?? null,
            'icon' => $data['icon'] ?? null,
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return true;
    }

    protected function upsertLocalFolder(User $user, Team $team, array $data): bool
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
            'name' => $data['name'] ?? 'Dossier',
            'slug' => $data['slug'] ?? Str::slug($data['name'] ?? 'dossier'),
            'color' => $data['color'] ?? null,
            'icon' => $data['icon'] ?? null,
            'description' => $data['description'] ?? null,
            'visibility' => $data['visibility'] ?? 'private',
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return true;
    }

    protected function upsertLocalTag(User $user, Team $team, array $data): bool
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

    protected function upsertLocalLink(User $user, Team $team, array $data): bool
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

            $this->syncTagsForLocalLink($team, $user, $link, $data['tags'] ?? []);

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

        $this->syncTagsForLocalLink($team, $user, $newLink, $data['tags'] ?? []);

        return true;
    }

    protected function syncTagsForLocalLink(Team $team, User $user, Link $link, array $tagNames): void
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

    protected function deleteLocalItem(Team $team, array $item): bool
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

        return false;
    }

    protected function resolveTeam(User $user, SyncSetting $settings): ?Team
    {
        if ($settings->team_id) {
            $team = Team::find($settings->team_id);
            if ($team) {
                return $team;
            }
        }

        return $user->teams()->first() ?? Team::first();
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
