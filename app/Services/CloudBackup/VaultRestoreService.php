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
        $categoryMap = []; // old_id => new_id, slug => id, uuid => id, name => id
        if ($categoriesJson) {
            $categories = json_decode($categoriesJson, true) ?: [];
            foreach ($categories as $cat) {
                if (empty($cat['name'])) {
                    continue;
                }
                $slug = $cat['slug'] ?? Str::slug($cat['name']);
                $category = Category::withoutGlobalScopes()->where('team_id', $targetTeam->id)->where('slug', $slug)->first();
                if (! $category && ! empty($cat['uuid'])) {
                    $category = Category::withoutGlobalScopes()->where('team_id', $targetTeam->id)->where('uuid', $cat['uuid'])->first();
                }

                if (! $category) {
                    $category = Category::create([
                        'uuid' => $cat['uuid'] ?? (string) Str::uuid(),
                        'team_id' => $targetTeam->id,
                        'user_id' => $user->id,
                        'name' => $cat['name'],
                        'slug' => $slug,
                        'icon' => $cat['icon'] ?? null,
                        'color' => $cat['color'] ?? '#6B7280',
                        'description' => $cat['description'] ?? null,
                        'sort_order' => (int) ($cat['sort_order'] ?? 0),
                    ]);
                    $categoriesCreated++;
                }

                if (isset($cat['id'])) {
                    $categoryMap[$cat['id']] = $category->id;
                    $categoryMap[(string) $cat['id']] = $category->id;
                }
                if (! empty($cat['uuid'])) {
                    $categoryMap[$cat['uuid']] = $category->id;
                }
                $categoryMap[$slug] = $category->id;
                $categoryMap[$cat['name']] = $category->id;
            }
        }

        // 2. Restauration des Dossiers
        $folderMap = []; // old_id => new_id, slug => id, uuid => id, name => id
        if ($foldersJson) {
            $folders = json_decode($foldersJson, true) ?: [];
            foreach ($folders as $fol) {
                if (empty($fol['name'])) {
                    continue;
                }
                $slug = $fol['slug'] ?? Str::slug($fol['name']);
                $folder = Folder::withoutGlobalScopes()->where('team_id', $targetTeam->id)->where('slug', $slug)->first();
                if (! $folder && ! empty($fol['uuid'])) {
                    $folder = Folder::withoutGlobalScopes()->where('team_id', $targetTeam->id)->where('uuid', $fol['uuid'])->first();
                }

                $catId = null;
                if (! empty($fol['category_id']) && isset($categoryMap[$fol['category_id']])) {
                    $catId = $categoryMap[$fol['category_id']];
                } elseif (! empty($fol['category']['slug']) && isset($categoryMap[$fol['category']['slug']])) {
                    $catId = $categoryMap[$fol['category']['slug']];
                } elseif (! empty($fol['category']['uuid']) && isset($categoryMap[$fol['category']['uuid']])) {
                    $catId = $categoryMap[$fol['category']['uuid']];
                }

                if (! $folder) {
                    $folder = Folder::create([
                        'uuid' => $fol['uuid'] ?? (string) Str::uuid(),
                        'team_id' => $targetTeam->id,
                        'user_id' => $user->id,
                        'category_id' => $catId,
                        'name' => $fol['name'],
                        'slug' => $slug,
                        'icon' => $fol['icon'] ?? null,
                        'color' => $fol['color'] ?? null,
                        'description' => $fol['description'] ?? null,
                        'visibility' => $fol['visibility'] ?? 'private',
                        'sort_order' => (int) ($fol['sort_order'] ?? 0),
                    ]);
                    $foldersCreated++;
                } else {
                    $folder->update([
                        'name' => $fol['name'],
                        'category_id' => $catId ?? $folder->category_id,
                        'icon' => $fol['icon'] ?? $folder->icon,
                        'color' => $fol['color'] ?? $folder->color,
                        'description' => $fol['description'] ?? $folder->description,
                        'visibility' => $fol['visibility'] ?? $folder->visibility,
                        'sort_order' => isset($fol['sort_order']) ? (int) $fol['sort_order'] : $folder->sort_order,
                    ]);
                }

                if (isset($fol['id'])) {
                    $folderMap[$fol['id']] = $folder->id;
                    $folderMap[(string) $fol['id']] = $folder->id;
                }
                if (! empty($fol['uuid'])) {
                    $folderMap[$fol['uuid']] = $folder->id;
                }
                $folderMap[$slug] = $folder->id;
                $folderMap[$fol['name']] = $folder->id;
            }
        }

        // 3. Restauration des Tags
        $tagMap = []; // name => id, slug => id
        if ($tagsJson) {
            $tags = json_decode($tagsJson, true) ?: [];
            foreach ($tags as $t) {
                if (empty($t['name'])) {
                    continue;
                }
                $slug = $t['slug'] ?? Str::slug($t['name']);
                $tag = Tag::withoutGlobalScopes()->where('team_id', $targetTeam->id)->where('slug', $slug)->first();
                if (! $tag) {
                    $tag = Tag::create([
                        'uuid' => $t['uuid'] ?? (string) Str::uuid(),
                        'team_id' => $targetTeam->id,
                        'user_id' => $user->id,
                        'name' => $t['name'],
                        'slug' => $slug,
                        'color' => $t['color'] ?? null,
                    ]);
                    $tagsCreated++;
                }

                $tagMap[$t['name']] = $tag->id;
                $tagMap[$slug] = $tag->id;
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

                $existing = Link::withoutGlobalScopes()->where('user_id', $user->id)
                    ->where('url_hash', $urlHash)
                    ->first();

                if ($existing && ! $overwrite) {
                    $skippedLinks++;

                    continue;
                }

                try {
                    // Résolution du dossier
                    $folderId = null;
                    if (! empty($item['folder'])) {
                        if (is_array($item['folder'])) {
                            if (! empty($item['folder']['slug']) && isset($folderMap[$item['folder']['slug']])) {
                                $folderId = $folderMap[$item['folder']['slug']];
                            } elseif (! empty($item['folder']['id']) && isset($folderMap[$item['folder']['id']])) {
                                $folderId = $folderMap[$item['folder']['id']];
                            } elseif (! empty($item['folder']['uuid']) && isset($folderMap[$item['folder']['uuid']])) {
                                $folderId = $folderMap[$item['folder']['uuid']];
                            } elseif (! empty($item['folder']['name']) && isset($folderMap[$item['folder']['name']])) {
                                $folderId = $folderMap[$item['folder']['name']];
                            } elseif (! empty($item['folder']['name'])) {
                                $fSlug = Str::slug($item['folder']['name']);
                                $f = Folder::withoutGlobalScopes()->where('team_id', $targetTeam->id)->where('slug', $fSlug)->first();
                                if (! $f) {
                                    $f = Folder::create(['team_id' => $targetTeam->id, 'slug' => $fSlug, 'user_id' => $user->id, 'name' => $item['folder']['name']]);
                                    $foldersCreated++;
                                }
                                $folderId = $f->id;
                                $folderMap[$item['folder']['name']] = $folderId;
                                $folderMap[$fSlug] = $folderId;
                            }
                        } elseif (is_string($item['folder'])) {
                            $folderName = trim($item['folder']);
                            if (isset($folderMap[$folderName])) {
                                $folderId = $folderMap[$folderName];
                            } else {
                                $fSlug = Str::slug($folderName);
                                if (isset($folderMap[$fSlug])) {
                                    $folderId = $folderMap[$fSlug];
                                } else {
                                    $f = Folder::withoutGlobalScopes()->where('team_id', $targetTeam->id)->where('slug', $fSlug)->first();
                                    if (! $f) {
                                        $f = Folder::create(['team_id' => $targetTeam->id, 'slug' => $fSlug, 'user_id' => $user->id, 'name' => $folderName]);
                                        $foldersCreated++;
                                    }
                                    $folderId = $f->id;
                                    $folderMap[$folderName] = $folderId;
                                    $folderMap[$fSlug] = $folderId;
                                }
                            }
                        }
                    } elseif (! empty($item['folder_uuid']) && isset($folderMap[$item['folder_uuid']])) {
                        $folderId = $folderMap[$item['folder_uuid']];
                    } elseif (! empty($item['folder_id']) && isset($folderMap[$item['folder_id']])) {
                        $folderId = $folderMap[$item['folder_id']];
                    }

                    // Résolution de la catégorie
                    $categoryId = null;
                    if (! empty($item['category'])) {
                        if (is_array($item['category'])) {
                            if (! empty($item['category']['slug']) && isset($categoryMap[$item['category']['slug']])) {
                                $categoryId = $categoryMap[$item['category']['slug']];
                            } elseif (! empty($item['category']['id']) && isset($categoryMap[$item['category']['id']])) {
                                $categoryId = $categoryMap[$item['category']['id']];
                            } elseif (! empty($item['category']['uuid']) && isset($categoryMap[$item['category']['uuid']])) {
                                $categoryId = $categoryMap[$item['category']['uuid']];
                            } elseif (! empty($item['category']['name']) && isset($categoryMap[$item['category']['name']])) {
                                $categoryId = $categoryMap[$item['category']['name']];
                            }
                        } elseif (is_string($item['category']) && isset($categoryMap[$item['category']])) {
                            $categoryId = $categoryMap[$item['category']];
                        }
                    } elseif (! empty($item['category_uuid']) && isset($categoryMap[$item['category_uuid']])) {
                        $categoryId = $categoryMap[$item['category_uuid']];
                    } elseif (! empty($item['category_id']) && isset($categoryMap[$item['category_id']])) {
                        $categoryId = $categoryMap[$item['category_id']];
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
                            $t = Tag::withoutGlobalScopes()->where('team_id', $targetTeam->id)->where('slug', $tSlug)->first();
                            if (! $t) {
                                $t = Tag::create(['team_id' => $targetTeam->id, 'slug' => $tSlug, 'user_id' => $user->id, 'name' => $tagName]);
                            }
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
