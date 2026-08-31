<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\GenerateLinkAiSummaryJob;
use App\Models\Folder;
use App\Models\Link;
use App\Models\Tag;
use App\Models\User;
use DOMElement;
use DOMNode;
use Illuminate\Support\Str;
use Throwable;

class BookmarksImportService
{
    public function __construct(
        protected ContentDetectionService $contentDetection
    ) {}

    /**
     * Importe des signets depuis une chaîne de contenu (HTML Netscape, JSON ou CSV).
     *
     * @return array{imported_count: int, skipped_count: int, folders_created: int, errors: array<string>}
     */
    public function importFromContent(
        string $content,
        string $originalExtension,
        User $user,
        int $teamId,
        ?int $defaultFolderId = null,
        bool $generateAiSummary = false
    ): array {
        if (empty(trim($content))) {
            return [
                'imported_count' => 0,
                'skipped_count' => 0,
                'folders_created' => 0,
                'errors' => ['Le contenu est vide ou illisible.'],
            ];
        }

        $ext = strtolower($originalExtension);

        if (in_array($ext, ['html', 'htm']) || str_contains($content, '<!DOCTYPE NETSCAPE-Bookmark-file-1>')) {
            return $this->importFromNetscapeHtml($content, $user, $teamId, $defaultFolderId, $generateAiSummary);
        }

        if ($ext === 'json' || (str_starts_with(trim($content), '[') || str_starts_with(trim($content), '{'))) {
            return $this->importFromJson($content, $user, $teamId, $defaultFolderId, $generateAiSummary);
        }

        if ($ext === 'csv' || str_contains($content, ',') || str_contains($content, ';')) {
            return $this->importFromCsv($content, $user, $teamId, $defaultFolderId, $generateAiSummary);
        }

        return $this->importFromNetscapeHtml($content, $user, $teamId, $defaultFolderId, $generateAiSummary);
    }

    /**
     * Importe des signets depuis un fichier sur le disque.
     *
     * @return array{imported_count: int, skipped_count: int, folders_created: int, errors: array<string>}
     */
    public function importFromFile(
        string $filePath,
        string $originalExtension,
        User $user,
        int $teamId,
        ?int $defaultFolderId = null,
        bool $generateAiSummary = false
    ): array {
        if (! file_exists($filePath)) {
            return [
                'imported_count' => 0,
                'skipped_count' => 0,
                'folders_created' => 0,
                'errors' => ["Fichier introuvable sur le disque : {$filePath}"],
            ];
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return [
                'imported_count' => 0,
                'skipped_count' => 0,
                'folders_created' => 0,
                'errors' => ['Impossible de lire le contenu du fichier.'],
            ];
        }

        return $this->importFromContent($content, $originalExtension, $user, $teamId, $defaultFolderId, $generateAiSummary);
    }

    /**
     * Parse et importe les signets au format universel Netscape Bookmark HTML (Chrome, Firefox, Safari, Pocket...).
     *
     * @return array{imported_count: int, skipped_count: int, folders_created: int, errors: array<string>}
     */
    public function importFromNetscapeHtml(
        string $htmlContent,
        User $user,
        int $teamId,
        ?int $defaultFolderId = null,
        bool $generateAiSummary = false
    ): array {
        $importedCount = 0;
        $skippedCount = 0;
        $foldersCreated = 0;
        $errors = [];
        $folderCache = [];

        // Stack de dossiers pour gérer l'arborescence
        $folderStack = [];

        // Découper par lignes ou tokens HTML
        $lines = preg_split('/\r\n|\r|\n/', $htmlContent);
        if (empty($lines)) {
            return [
                'imported_count' => 0,
                'skipped_count' => 0,
                'folders_created' => 0,
                'errors' => ['Fichier vide.'],
            ];
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                continue;
            }

            // Détection de fin de dossier </DL>
            if (stripos($trimmed, '</DL>') !== false) {
                if (! empty($folderStack)) {
                    array_pop($folderStack);
                }
            }

            // Détection d'un nouveau dossier : <H3...>Nom du dossier</H3>
            if (preg_match('/<H3[^>]*>(.*?)<\/H3>/i', $trimmed, $h3Matches)) {
                $folderName = trim(strip_tags($h3Matches[1]));
                if (! empty($folderName) && ! in_array(strtolower($folderName), ['bookmarks', 'signets', 'barre de favoris', 'favoris', 'bookmarks bar', 'other bookmarks'])) {
                    $folderStack[] = $folderName;
                }
            }

            // Détection d'un lien : <A HREF="url"...>Titre</A>
            if (preg_match('/<A\s+([^>]*HREF=[\'"][^\'"]+[\'"][^>]*)>(.*?)<\/A>/i', $trimmed, $aMatches)) {
                $attrString = $aMatches[1];
                $rawTitle = $aMatches[2];

                // Extraire HREF
                if (! preg_match('/HREF=[\'"]([^\'"]+)[\'"]/i', $attrString, $hrefMatch)) {
                    continue;
                }

                $url = trim($hrefMatch[1]);
                if (empty($url) || ! preg_match('/^https?:\/\//i', $url)) {
                    continue;
                }

                $title = trim(strip_tags(html_entity_decode($rawTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?: $url;

                // Extraire TAGS
                $tags = [];
                if (preg_match('/TAGS=[\'"]([^\'"]+)[\'"]/i', $attrString, $tagsMatch)) {
                    $tags = array_filter(array_map('trim', explode(',', $tagsMatch[1])));
                }

                // Extraire ICON
                $icon = null;
                if (preg_match('/ICON=[\'"]([^\'"]+)[\'"]/i', $attrString, $iconMatch)) {
                    $icon = $iconMatch[1];
                }

                $urlHash = hash('sha256', $url);

                if (Link::query()->where('team_id', $teamId)->where('url_hash', $urlHash)->exists()) {
                    $skippedCount++;

                    continue;
                }

                // Résolution du dossier
                $folderId = $defaultFolderId;
                $currentFolderName = ! empty($folderStack) ? end($folderStack) : null;

                if ($currentFolderName) {
                    if (! isset($folderCache[$currentFolderName])) {
                        $folderSlug = Str::slug($currentFolderName);
                        $folder = Folder::query()
                            ->where('team_id', $teamId)
                            ->where('slug', $folderSlug)
                            ->first();

                        if (! $folder) {
                            $folder = Folder::create([
                                'user_id' => $user->id,
                                'team_id' => $teamId,
                                'name' => $currentFolderName,
                                'slug' => $folderSlug,
                            ]);
                            $foldersCreated++;
                        }

                        $folderCache[$currentFolderName] = $folder->id;
                    }

                    $folderId = $folderCache[$currentFolderName];
                }

                try {
                    $analysis = $this->contentDetection->analyze($url);
                    $meta = $analysis['metadata'] ?? [];
                    $contentType = $analysis['type'] ?? 'other';
                    $thumbnailUrl = $meta['image'] ?? $meta['og_image'] ?? null;
                    $faviconUrl = $icon ?: ($meta['favicon'] ?? (new WebPageMetadataService)->fetchFavicon($url));

                    $link = Link::create([
                        'user_id' => $user->id,
                        'team_id' => $teamId,
                        'url' => $url,
                        'url_hash' => $urlHash,
                        'title' => Str::limit($title, 255),
                        'content_type' => $contentType,
                        'folder_id' => $folderId,
                        'metadata' => $meta,
                        'thumbnail_url' => $thumbnailUrl,
                        'favicon_url' => $faviconUrl,
                        'ai_summary_status' => 'pending',
                    ]);

                    if (! empty($tags)) {
                        $this->syncTags($link, $tags, $user, $teamId);
                    }

                    if ($generateAiSummary) {
                        GenerateLinkAiSummaryJob::dispatch($link);
                    }

                    $importedCount++;
                } catch (Throwable $e) {
                    $errors[] = "Erreur sur {$url} : {$e->getMessage()}";
                }
            }
        }

        return [
            'imported_count' => $importedCount,
            'skipped_count' => $skippedCount,
            'folders_created' => $foldersCreated,
            'errors' => $errors,
        ];
    }

    /**
     * Parse et importe les signets au format JSON.
     *
     * @return array{imported_count: int, skipped_count: int, folders_created: int, errors: array<string>}
     */
    public function importFromJson(
        string $jsonContent,
        User $user,
        int $teamId,
        ?int $defaultFolderId = null,
        bool $generateAiSummary = false
    ): array {
        $importedCount = 0;
        $skippedCount = 0;
        $foldersCreated = 0;
        $errors = [];

        $data = json_decode($jsonContent, true);
        if (! is_array($data)) {
            return [
                'imported_count' => 0,
                'skipped_count' => 0,
                'folders_created' => 0,
                'errors' => ['Format JSON invalide.'],
            ];
        }

        // Si le JSON contient une clé racine (ex: "bookmarks" ou "links")
        $items = isset($data['bookmarks']) && is_array($data['bookmarks'])
            ? $data['bookmarks']
            : (isset($data['links']) && is_array($data['links']) ? $data['links'] : $data);

        $folderCache = [];

        foreach ($items as $item) {
            if (! is_array($item) || empty($item['url'])) {
                continue;
            }

            $url = trim((string) $item['url']);
            if (! preg_match('/^https?:\/\//i', $url)) {
                continue;
            }

            $urlHash = hash('sha256', $url);

            if (Link::where('team_id', $teamId)->where('url_hash', $urlHash)->exists()) {
                $skippedCount++;

                continue;
            }

            $title = ! empty($item['title']) ? trim((string) $item['title']) : $url;
            $description = ! empty($item['description']) ? trim((string) $item['description']) : null;
            $folderName = ! empty($item['folder']) ? trim((string) $item['folder']) : null;
            $folderId = $defaultFolderId;

            if ($folderName) {
                if (! isset($folderCache[$folderName])) {
                    $folderSlug = Str::slug($folderName);
                    $folder = Folder::firstOrCreate([
                        'team_id' => $teamId,
                        'slug' => $folderSlug,
                    ], [
                        'user_id' => $user->id,
                        'name' => $folderName,
                    ]);

                    if ($folder->wasRecentlyCreated) {
                        $foldersCreated++;
                    }

                    $folderCache[$folderName] = $folder->id;
                }

                $folderId = $folderCache[$folderName];
            }

            try {
                $analysis = $this->contentDetection->analyze($url);
                $meta = $analysis['metadata'] ?? [];
                $thumbnailUrl = $item['thumbnail_url'] ?? ($meta['image'] ?? $meta['og_image'] ?? null);
                $faviconUrl = $item['favicon_url'] ?? ($meta['favicon'] ?? (new WebPageMetadataService)->fetchFavicon($url));

                $link = Link::create([
                    'user_id' => $user->id,
                    'team_id' => $teamId,
                    'url' => $url,
                    'url_hash' => $urlHash,
                    'title' => Str::limit($title, 255),
                    'description' => $description,
                    'content_type' => $item['content_type'] ?? ($analysis['type'] ?? 'other'),
                    'folder_id' => $folderId,
                    'metadata' => $meta,
                    'thumbnail_url' => $thumbnailUrl,
                    'favicon_url' => $faviconUrl,
                    'is_favorite' => ! empty($item['is_favorite']),
                    'ai_summary_status' => 'pending',
                ]);

                if (! empty($item['tags'])) {
                    $tags = is_array($item['tags']) ? $item['tags'] : explode(',', (string) $item['tags']);
                    $this->syncTags($link, $tags, $user, $teamId);
                }

                if ($generateAiSummary) {
                    GenerateLinkAiSummaryJob::dispatch($link);
                }

                $importedCount++;
            } catch (Throwable $e) {
                $errors[] = "Erreur sur {$url} : {$e->getMessage()}";
            }
        }

        return [
            'imported_count' => $importedCount,
            'skipped_count' => $skippedCount,
            'folders_created' => $foldersCreated,
            'errors' => $errors,
        ];
    }

    /**
     * Parse et importe les signets au format CSV.
     *
     * @return array{imported_count: int, skipped_count: int, folders_created: int, errors: array<string>}
     */
    public function importFromCsv(
        string $csvContent,
        User $user,
        int $teamId,
        ?int $defaultFolderId = null,
        bool $generateAiSummary = false
    ): array {
        $lines = preg_split('/\r\n|\r|\n/', trim($csvContent));
        if (empty($lines)) {
            return [
                'imported_count' => 0,
                'skipped_count' => 0,
                'folders_created' => 0,
                'errors' => ['Fichier CSV vide.'],
            ];
        }

        // Détecter le délimiteur (, ou ;)
        $firstLine = $lines[0];
        $delimiter = str_contains($firstLine, ';') ? ';' : ',';

        $header = str_getcsv(array_shift($lines), $delimiter);
        $header = array_map(fn ($col) => strtolower(trim($col)), $header);

        $urlIndex = array_search('url', $header);
        if ($urlIndex === false) {
            $urlIndex = 0; // Fallback sur la 1ère colonne
        }

        $titleIndex = array_search('title', $header);
        $folderIndex = array_search('folder', $header);
        $tagsIndex = array_search('tags', $header);
        $descIndex = array_search('description', $header);

        $importedCount = 0;
        $skippedCount = 0;
        $foldersCreated = 0;
        $errors = [];
        $folderCache = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $row = str_getcsv($line, $delimiter);
            $url = isset($row[$urlIndex]) ? trim($row[$urlIndex]) : '';

            if (empty($url) || ! preg_match('/^https?:\/\//i', $url)) {
                continue;
            }

            $urlHash = hash('sha256', $url);
            if (Link::where('team_id', $teamId)->where('url_hash', $urlHash)->exists()) {
                $skippedCount++;

                continue;
            }

            $title = ($titleIndex !== false && isset($row[$titleIndex])) ? trim($row[$titleIndex]) : $url;
            $description = ($descIndex !== false && isset($row[$descIndex])) ? trim($row[$descIndex]) : null;
            $folderName = ($folderIndex !== false && isset($row[$folderIndex])) ? trim($row[$folderIndex]) : null;
            $folderId = $defaultFolderId;

            if ($folderName) {
                if (! isset($folderCache[$folderName])) {
                    $folderSlug = Str::slug($folderName);
                    $folder = Folder::firstOrCreate([
                        'team_id' => $teamId,
                        'slug' => $folderSlug,
                    ], [
                        'user_id' => $user->id,
                        'name' => $folderName,
                    ]);

                    if ($folder->wasRecentlyCreated) {
                        $foldersCreated++;
                    }

                    $folderCache[$folderName] = $folder->id;
                }

                $folderId = $folderCache[$folderName];
            }

            try {
                $analysis = $this->contentDetection->analyze($url);
                $meta = $analysis['metadata'] ?? [];
                $thumbnailUrl = $meta['image'] ?? $meta['og_image'] ?? null;
                $faviconUrl = $meta['favicon'] ?? (new WebPageMetadataService)->fetchFavicon($url);

                $link = Link::create([
                    'user_id' => $user->id,
                    'team_id' => $teamId,
                    'url' => $url,
                    'url_hash' => $urlHash,
                    'title' => Str::limit($title ?: $url, 255),
                    'description' => $description,
                    'content_type' => $analysis['type'] ?? 'other',
                    'folder_id' => $folderId,
                    'metadata' => $meta,
                    'thumbnail_url' => $thumbnailUrl,
                    'favicon_url' => $faviconUrl,
                    'ai_summary_status' => 'pending',
                ]);

                if ($tagsIndex !== false && ! empty($row[$tagsIndex])) {
                    $tags = explode(',', $row[$tagsIndex]);
                    $this->syncTags($link, $tags, $user, $teamId);
                }

                if ($generateAiSummary) {
                    GenerateLinkAiSummaryJob::dispatch($link);
                }

                $importedCount++;
            } catch (Throwable $e) {
                $errors[] = "Erreur sur {$url} : {$e->getMessage()}";
            }
        }

        return [
            'imported_count' => $importedCount,
            'skipped_count' => $skippedCount,
            'folders_created' => $foldersCreated,
            'errors' => $errors,
        ];
    }

    /**
     * Recherche le nom du dossier parent dans l'arbre Netscape HTML.
     */
    protected function findParentFolderName(DOMNode $node): ?string
    {
        $current = $node->parentNode;

        while ($current) {
            if ($current instanceof DOMElement && strtoupper($current->tagName) === 'DL') {
                // Chercher l'élément <H3> frère précédent ou dans le parent <DT>
                $prev = $current->previousSibling;
                while ($prev) {
                    if ($prev instanceof DOMElement && strtoupper($prev->tagName) === 'H3') {
                        $name = trim($prev->textContent);
                        if (! empty($name) && ! in_array(strtolower($name), ['bookmarks', 'signets', 'barre de favoris', 'favoris'])) {
                            return $name;
                        }
                    }
                    $prev = $prev->previousSibling;
                }

                // Vérifier si le parent est un <DT> contenant un <H3>
                $dtParent = $current->parentNode;
                if ($dtParent instanceof DOMElement && strtoupper($dtParent->tagName) === 'DT') {
                    $h3List = $dtParent->getElementsByTagName('h3');
                    if ($h3List->length > 0) {
                        $name = trim($h3List->item(0)->textContent);
                        if (! empty($name) && ! in_array(strtolower($name), ['bookmarks', 'signets', 'barre de favoris', 'favoris'])) {
                            return $name;
                        }
                    }
                }
            }

            $current = $current->parentNode;
        }

        return null;
    }

    /**
     * Synchronise les tags sur le lien.
     *
     * @param  array<string>  $tags
     */
    protected function syncTags(Link $link, array $tags, User $user, int $teamId): void
    {
        $tagIds = [];
        $cleanNames = [];

        foreach ($tags as $tagName) {
            $name = trim((string) $tagName);
            if (empty($name)) {
                continue;
            }

            $tag = Tag::firstOrCreate([
                'user_id' => $user->id,
                'team_id' => $teamId,
                'slug' => Str::slug($name),
            ], [
                'name' => $name,
            ]);

            $tagIds[] = $tag->id;
            $cleanNames[] = $name;
        }

        if (! empty($tagIds)) {
            $link->tags()->sync($tagIds);
            $link->update(['tags' => implode(', ', $cleanNames)]);
        }
    }
}
