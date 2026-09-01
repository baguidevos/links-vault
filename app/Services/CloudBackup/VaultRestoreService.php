<?php

declare(strict_types=1);

namespace App\Services\CloudBackup;

use App\Models\Category;
use App\Models\Folder;
use App\Models\Link;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

class VaultRestoreService
{
    /**
     * Restaure un Vault complet depuis le contenu binaire d'une archive ZIP.
     *
     * @return array{
     *     imported_links: int,
     *     skipped_links: int,
     *     folders_created: int,
     *     categories_created: int,
     *     tags_created: int,
     *     errors: array<string>
     * }
     */
    public function restoreFromZip(
        string $zipBinaryContent,
        Model|Team $targetTeam,
        User $user,
        bool $overwrite = false
    ): array {
        $tempPath = tempnam(sys_get_temp_dir(), 'lv_restore_');
        file_put_contents($tempPath, $zipBinaryContent);

        $zip = new ZipArchive;
        if ($zip->open($tempPath) !== true) {
            @unlink($tempPath);
            throw new \RuntimeException('Le fichier fourni n\'est pas une archive ZIP valide.');
        }

        $manifestContent = $zip->getFromName('manifest.json');
        if ($manifestContent === false) {
            $zip->close();
            @unlink($tempPath);
            throw new \RuntimeException('Archive invalide : fichier manifest.json manquant.');
        }

        $manifest = json_decode($manifestContent, true);
        if (! is_array($manifest) || ($manifest['app'] ?? '') !== 'LinksVault') {
            $zip->close();
            @unlink($tempPath);
            throw new \RuntimeException('Archive non reconnue comme une sauvegarde officielle LinksVault.');
        }

        $linksJson = $zip->getFromName('links.json');
        $foldersJson = $zip->getFromName('folders.json');
        $categoriesJson = $zip->getFromName('categories.json');
        $tagsJson = $zip->getFromName('tags.json');

        $zip->close();
        @unlink($tempPath);

        $importedLinks = 0;
        $skippedLinks = 0;
        $foldersCreated = 0;
        $categoriesCreated = 0;
        $tagsCreated = 0;
        $errors = [];

        // 1. Restauration des Catégories
        $categoryMap = []; // old_id => new_id or slug => id
        if ($categoriesJson) {
            $categories = json_decode($categoriesJson, true) ?: [];
            foreach ($categories as $cat) {
                if (empty($cat['name'])) {
                    continue;
                }
                $slug = $cat['slug'] ?? Str::slug($cat['name']);
                $category = Category::firstOrCreate([
                    'team_id' => $targetTeam->id,
                    'slug' => $slug,
                ], [
                    'user_id' => $user->id,
                    'name' => $cat['name'],
                    'icon' => $cat['icon'] ?? null,
                    'color' => $cat['color'] ?? '#6B7280',
                ]);

                if ($category->wasRecentlyCreated) {
                    $categoriesCreated++;
                }

                if (isset($cat['id'])) {
                    $categoryMap[$cat['id']] = $category->id;
                }
                $categoryMap[$slug] = $category->id;
            }
        }

        // 2. Restauration des Dossiers
        $folderMap = []; // old_id => new_id or slug => id
        if ($foldersJson) {
            $folders = json_decode($foldersJson, true) ?: [];
            foreach ($folders as $fol) {
                if (empty($fol['name'])) {
                    continue;
                }
                $slug = $fol['slug'] ?? Str::slug($fol['name']);
                $folder = Folder::firstOrCreate([
                    'team_id' => $targetTeam->id,
                    'slug' => $slug,
                ], [
                    'user_id' => $user->id,
                    'name' => $fol['name'],
                    'icon' => $fol['icon'] ?? null,
                    'color' => $fol['color'] ?? null,
                    'description' => $fol['description'] ?? null,
                ]);

                if ($folder->wasRecentlyCreated) {
                    $foldersCreated++;
                }

                if (isset($fol['id'])) {
                    $folderMap[$fol['id']] = $folder->id;
                }
                $folderMap[$slug] = $folder->id;
            }
        }

        // 3. Restauration des Tags
        $tagMap = []; // name => id
        if ($tagsJson) {
            $tags = json_decode($tagsJson, true) ?: [];
            foreach ($tags as $t) {
                if (empty($t['name'])) {
                    continue;
                }
                $slug = $t['slug'] ?? Str::slug($t['name']);
                $tag = Tag::firstOrCreate([
                    'team_id' => $targetTeam->id,
                    'slug' => $slug,
                ], [
                    'user_id' => $user->id,
                    'name' => $t['name'],
                    'color' => $t['color'] ?? null,
                ]);

                if ($tag->wasRecentlyCreated) {
                    $tagsCreated++;
                }

                $tagMap[$t['name']] = $tag->id;
            }
        }

        // 4. Restauration des Liens
        if ($linksJson) {
            $links = json_decode($linksJson, true) ?: [];
            foreach ($links as $item) {
                if (empty($item['url'])) {
                    continue;
                }

                $url = trim($item['url']);
                $urlHash = hash('sha256', $url);

                $existing = Link::where('user_id', $user->id)
                    ->where('url_hash', $urlHash)
                    ->first();

                if ($existing && ! $overwrite) {
                    $skippedLinks++;

                    continue;
                }

                try {
                    // Résolution du dossier
                    $folderId = null;
                    if (! empty($item['folder']['slug']) && isset($folderMap[$item['folder']['slug']])) {
                        $folderId = $folderMap[$item['folder']['slug']];
                    } elseif (! empty($item['folder']['name'])) {
                        $fSlug = Str::slug($item['folder']['name']);
                        $f = Folder::firstOrCreate(['team_id' => $targetTeam->id, 'slug' => $fSlug], [
                            'user_id' => $user->id,
                            'name' => $item['folder']['name'],
                        ]);
                        $folderId = $f->id;
                    }

                    // Résolution de la catégorie
                    $categoryId = null;
                    if (! empty($item['category']['slug']) && isset($categoryMap[$item['category']['slug']])) {
                        $categoryId = $categoryMap[$item['category']['slug']];
                    }

                    $linkAttributes = [
                        'user_id' => $user->id,
                        'team_id' => $targetTeam->id,
                        'url' => $url,
                        'url_hash' => $urlHash,
                        'title' => Str::limit($item['title'] ?? $url, 255),
                        'description' => $item['description'] ?? null,
                        'objective' => $item['objective'] ?? null,
                        'content_type' => $item['content_type'] ?? 'other',
                        'visibility' => $item['visibility'] ?? 'private',
                        'folder_id' => $folderId,
                        'category_id' => $categoryId,
                        'favicon_url' => $item['favicon_url'] ?? null,
                        'thumbnail_url' => $item['thumbnail_url'] ?? null,
                        'metadata' => $item['metadata'] ?? [],
                        'is_favorite' => ! empty($item['is_favorite']),
                        'is_archived' => ! empty($item['is_archived']),
                        'visit_count' => (int) ($item['visit_count'] ?? 0),
                        'ai_summary_status' => ! empty($item['metadata']['ai_analysis']) ? 'completed' : 'pending',
                    ];

                    if ($existing && $overwrite) {
                        $existing->update($linkAttributes);
                        $link = $existing;
                    } else {
                        $link = Link::create($linkAttributes);
                    }

                    // Synchronisation des tags
                    if (! empty($item['tags']) && is_array($item['tags'])) {
                        $tagIds = [];
                        foreach ($item['tags'] as $tagName) {
                            $tSlug = Str::slug($tagName);
                            $t = Tag::firstOrCreate(['team_id' => $targetTeam->id, 'slug' => $tSlug], [
                                'user_id' => $user->id,
                                'name' => $tagName,
                            ]);
                            $tagIds[] = $t->id;
                        }
                        $link->tags()->sync($tagIds);
                    }

                    $importedLinks++;
                } catch (Throwable $e) {
                    $errors[] = "Erreur sur {$url} : {$e->getMessage()}";
                }
            }
        }

        return [
            'imported_links' => $importedLinks,
            'skipped_links' => $skippedLinks,
            'folders_created' => $foldersCreated,
            'categories_created' => $categoriesCreated,
            'tags_created' => $tagsCreated,
            'errors' => $errors,
        ];
    }
}
