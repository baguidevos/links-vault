<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Folder;
use App\Models\Link;
use App\Models\User;

class BookmarksExportService
{
    /**
     * Exporte les signets d'une équipe au format universel Netscape Bookmark HTML.
     */
    public function exportToHtml(User $user, int $teamId): string
    {
        $links = Link::query()
            ->withoutGlobalScopes()
            ->where(function ($q) use ($teamId, $user) {
                $q->where('team_id', $teamId);
                if ($user->id) {
                    $q->orWhere(function ($sq) use ($user) {
                        $sq->whereNull('team_id')->where('user_id', $user->id);
                    });
                }
            })
            ->with(['folder', 'tags'])
            ->get();

        $folders = Folder::query()
            ->withoutGlobalScopes()
            ->where(function ($q) use ($teamId, $user) {
                $q->where('team_id', $teamId);
                if ($user->id) {
                    $q->orWhere(function ($sq) use ($user) {
                        $sq->whereNull('team_id')->where('user_id', $user->id);
                    });
                }
            })
            ->with(['links' => function ($q) use ($teamId, $user) {
                $q->withoutGlobalScopes()->where(function ($sq) use ($teamId, $user) {
                    $sq->where('team_id', $teamId);
                    if ($user->id) {
                        $sq->orWhere(function ($ssq) use ($user) {
                            $ssq->whereNull('team_id')->where('user_id', $user->id);
                        });
                    }
                })->with('tags');
            }])
            ->get();

        $html = "<!DOCTYPE NETSCAPE-Bookmark-file-1>\n";
        $html .= "<!-- This is an automatically generated file. It will be read and overwritten. Do Not Edit! -->\n";
        $html .= "<META HTTP-EQUIV=\"Content-Type\" CONTENT=\"text/html; charset=UTF-8\">\n";
        $html .= "<TITLE>Signets LinksVault</TITLE>\n";
        $html .= "<H1>Signets LinksVault</H1>\n";
        $html .= "<DL><p>\n";

        // 1. Liens sans dossier (Racine) ou dont le dossier n'est pas dans la liste des dossiers exportés
        $folderIds = $folders->pluck('id')->all();
        $rootLinks = $links->filter(fn (Link $link) => empty($link->folder_id) || ! in_array($link->folder_id, $folderIds, true));
        foreach ($rootLinks as $link) {
            $html .= $this->formatLinkHtml($link);
        }

        // 2. Liens organisés par dossiers (y compris dossiers vides)
        foreach ($folders as $folder) {
            $html .= '    <DT><H3 ADD_DATE="'.($folder->created_at?->timestamp ?? time()).'" LAST_MODIFIED="'.($folder->updated_at?->timestamp ?? time()).'">'.htmlspecialchars($folder->name).'</H3>'."\n";
            $html .= "    <DL><p>\n";

            foreach ($folder->links as $link) {
                $html .= '    '.$this->formatLinkHtml($link);
            }

            $html .= "    </DL><p>\n";
        }

        $html .= "</DL><p>\n";

        return $html;
    }

    /**
     * Exporte les signets et dossiers au format JSON.
     */
    public function exportToJson(User $user, int $teamId): string
    {
        $links = Link::query()
            ->withoutGlobalScopes()
            ->where(function ($q) use ($teamId, $user) {
                $q->where('team_id', $teamId);
                if ($user->id) {
                    $q->orWhere(function ($sq) use ($user) {
                        $sq->whereNull('team_id')->where('user_id', $user->id);
                    });
                }
            })
            ->with(['folder', 'tags', 'category'])
            ->get();

        $folders = Folder::query()
            ->withoutGlobalScopes()
            ->where(function ($q) use ($teamId, $user) {
                $q->where('team_id', $teamId);
                if ($user->id) {
                    $q->orWhere(function ($sq) use ($user) {
                        $sq->whereNull('team_id')->where('user_id', $user->id);
                    });
                }
            })
            ->with(['category', 'links'])
            ->get();

        $foldersData = $folders->map(fn (Folder $f) => [
            'uuid' => $f->uuid,
            'name' => $f->name,
            'slug' => $f->slug,
            'color' => $f->color,
            'icon' => $f->icon,
            'description' => $f->description,
            'visibility' => $f->visibility?->value ?? (string) $f->visibility,
            'category' => $f->category?->name,
            'sort_order' => $f->sort_order,
            'links_count' => $f->links->count(),
            'created_at' => $f->created_at?->toIso8601String(),
            'updated_at' => $f->updated_at?->toIso8601String(),
        ]);

        $linksData = $links->map(fn (Link $link) => [
            'uuid' => $link->uuid,
            'title' => $link->title,
            'url' => $link->url,
            'description' => $link->description,
            'objective' => $link->objective,
            'folder' => $link->folder?->name,
            'folder_uuid' => $link->folder?->uuid,
            'category' => $link->category?->name,
            'category_uuid' => $link->category?->uuid,
            'tags' => $this->getLinkTags($link),
            'content_type' => $link->content_type?->value ?? (string) $link->content_type,
            'is_favorite' => (bool) $link->is_favorite,
            'is_archived' => (bool) $link->is_archived,
            'ai_summary' => $link->ai_summary,
            'favicon_url' => $link->favicon_url,
            'thumbnail_url' => $link->thumbnail_url,
            'created_at' => $link->created_at?->toIso8601String(),
            'updated_at' => $link->updated_at?->toIso8601String(),
        ]);

        return json_encode([
            'exported_at' => now()->toIso8601String(),
            'folders_count' => $foldersData->count(),
            'links_count' => $linksData->count(),
            'folders' => $foldersData,
            'links' => $linksData,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Exporte les signets au format CSV.
     */
    public function exportToCsv(User $user, int $teamId): string
    {
        $links = Link::query()
            ->withoutGlobalScopes()
            ->where(function ($q) use ($teamId, $user) {
                $q->where('team_id', $teamId);
                if ($user->id) {
                    $q->orWhere(function ($sq) use ($user) {
                        $sq->whereNull('team_id')->where('user_id', $user->id);
                    });
                }
            })
            ->with(['folder', 'tags', 'category'])
            ->get();

        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['URL', 'Titre', 'Description', 'Dossier', 'Catégorie', 'Tags', 'Favori', 'Date']);

        foreach ($links as $link) {
            fputcsv($output, [
                $link->url,
                $link->title,
                $link->description,
                $link->folder?->name,
                $link->category?->name,
                implode(', ', $this->getLinkTags($link)),
                $link->is_favorite ? 'Oui' : 'Non',
                $link->created_at?->format('Y-m-d H:i:s'),
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv ?: '';
    }

    /**
     * Formate une balise <DT><A> Netscape.
     */
    protected function formatLinkHtml(Link $link): string
    {
        $addDate = $link->created_at?->timestamp ?? time();
        $tags = implode(',', $this->getLinkTags($link));
        $tagsAttr = $tags ? ' TAGS="'.htmlspecialchars($tags).'"' : '';
        $iconAttr = $link->favicon_url ? ' ICON="'.htmlspecialchars($link->favicon_url).'"' : '';

        return '    <DT><A HREF="'.htmlspecialchars($link->url).'" ADD_DATE="'.$addDate.'"'.$iconAttr.$tagsAttr.'>'.htmlspecialchars($link->title ?: $link->url)."</A>\n";
    }

    /**
     * Récupère les noms des tags de manière sûre.
     *
     * @return array<string>
     */
    protected function getLinkTags(Link $link): array
    {
        if ($link->relationLoaded('tags') && is_iterable($link->getRelation('tags'))) {
            return $link->getRelation('tags')->pluck('name')->all();
        }

        return $link->tags()->pluck('name')->all();
    }
}
