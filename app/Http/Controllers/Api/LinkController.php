<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PreviewLinkRequest;
use App\Http\Requests\Api\StoreLinkRequest;
use App\Jobs\GenerateLinkAiSummaryJob;
use App\Models\Category;
use App\Models\Folder;
use App\Models\Link;
use App\Models\Tag;
use App\Models\Team;
use App\Services\AIService;
use App\Services\ContentDetectionService;
use App\Services\SubscriptionQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class LinkController extends Controller
{
    public function __construct(
        protected ContentDetectionService $contentDetection,
        protected AIService $aiService,
    ) {}

    /**
     * Preview and extract metadata from a given URL.
     */
    public function preview(PreviewLinkRequest $request): JsonResponse
    {
        $url = $request->validated('url');
        $analysis = $this->contentDetection->analyze($url);
        $type = $analysis['type'] ?? 'other';
        $metadata = $analysis['metadata'] ?? [];

        $title = $metadata['title'] ?? null;
        if (empty($title)) {
            $title = $this->contentDetection->generateTitleFromUrl($url, $type);
        }

        $description = $metadata['description'] ?? null;
        if ($type === 'youtube') {
            $ytDesc = $this->contentDetection->getYoutubeVideoDescription($url);
            if (! empty($ytDesc)) {
                $description = $ytDesc;
            }
        }

        $thumbnailUrl = $metadata['image'] ?? $metadata['og_image'] ?? null;
        $faviconUrl = $metadata['favicon'] ?? null;

        return response()->json([
            'url' => $url,
            'content_type' => $type,
            'title' => $title,
            'description' => $description,
            'thumbnail_url' => $thumbnailUrl,
            'favicon_url' => $faviconUrl,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Store a newly created link from the Chrome extension or API.
     */
    public function store(StoreLinkRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $url = $data['url'];
        $urlHash = hash('sha256', $url);

        // Determine Team
        $teamId = $data['team_id'] ?? $user->current_team_id;
        if (! $teamId) {
            $firstTeam = $user->teams()->first();
            $teamId = $firstTeam?->id;
        }

        // Quota check
        $quotaService = app(SubscriptionQuotaService::class);
        $team = $teamId ? Team::find($teamId) : null;
        if (! $quotaService->canCreateLink($user, $team)) {
            return response()->json([
                'message' => "Limite de liens atteinte pour votre plan ({$quotaService->getLinksLimit($user)} liens max). Passez au plan Pro pour un stockage illimité.",
                'code' => 'QUOTA_EXCEEDED',
            ], 403);
        }

        // Resolve folder and category
        $folderId = $data['folder_id'] ?? null;
        if (! $folderId && ! empty($data['folder_uuid'])) {
            $folderId = Folder::where('uuid', $data['folder_uuid'])->value('id');
        }
        if (! $folderId && ! empty($data['folder_name'])) {
            $folderId = Folder::where('team_id', $teamId)->where('name', $data['folder_name'])->value('id')
                ?? Folder::where('user_id', $user->id)->where('name', $data['folder_name'])->value('id');
        }

        $categoryId = $data['category_id'] ?? null;
        if (! $categoryId && ! empty($data['category_uuid'])) {
            $categoryId = Category::where('uuid', $data['category_uuid'])->value('id');
        }
        if (! $categoryId && ! empty($data['category_name'])) {
            $categoryId = Category::where('team_id', $teamId)->where('name', $data['category_name'])->value('id')
                ?? Category::where('user_id', $user->id)->where('name', $data['category_name'])->value('id');
        }
        if ($folderId && empty($categoryId)) {
            $folder = Folder::find($folderId);
            if ($folder && $folder->category_id) {
                $categoryId = $folder->category_id;
            }
        }

        // Check for duplicate
        $existing = Link::where('user_id', $user->id)
            ->where('url_hash', $urlHash)
            ->first();

        if ($existing) {
            $updates = [];
            if ($folderId !== null && $existing->folder_id !== $folderId) {
                $updates['folder_id'] = $folderId;
            }
            if ($categoryId !== null && $existing->category_id !== $categoryId) {
                $updates['category_id'] = $categoryId;
            }
            if (isset($data['is_favorite'])) {
                $updates['is_favorite'] = (bool) $data['is_favorite'];
            }
            if (! empty($updates)) {
                $existing->update($updates);
            }

            if (! empty($data['tags']) && is_array($data['tags'])) {
                $tagIds = [];
                $tagNames = [];
                foreach ($data['tags'] as $tagItem) {
                    $tagName = trim((string) $tagItem);
                    if (empty($tagName)) {
                        continue;
                    }
                    $tag = Tag::firstOrCreate([
                        'user_id' => $user->id,
                        'team_id' => $existing->team_id,
                        'slug' => Str::slug($tagName),
                    ], [
                        'name' => $tagName,
                    ]);
                    $tagIds[] = $tag->id;
                    $tagNames[] = $tagName;
                }
                if (! empty($tagIds)) {
                    $existing->tags()->syncWithoutDetaching($tagIds);
                    $existing->update(['tags' => implode(', ', $tagNames)]);
                }
            }

            $tenant = $existing->team;
            $webUrl = $tenant
                ? url("/app/{$tenant->slug}/links/{$existing->id}")
                : url('/app');

            return response()->json([
                'message' => 'Lien déjà présent dans votre coffre-fort (mis à jour dans le dossier choisi).',
                'link' => $existing->load('tags', 'category', 'folder'),
                'vault_url' => $webUrl,
                'is_duplicate' => true,
            ], 200);
        }

        // Auto-extract metadata if fields are missing
        $type = $data['content_type'] ?? null;
        $metadata = [];
        $title = $data['title'] ?? null;
        $description = $data['description'] ?? null;
        $faviconUrl = $data['favicon_url'] ?? null;
        $thumbnailUrl = $data['thumbnail_url'] ?? null;

        if (empty($title) || empty($type) || empty($faviconUrl)) {
            $analysis = $this->contentDetection->analyze($url);
            $type = $type ?: ($analysis['type'] ?? 'other');
            $metadata = $analysis['metadata'] ?? [];

            if (empty($title)) {
                $title = $metadata['title'] ?? $this->contentDetection->generateTitleFromUrl($url, $type);
            }

            if (empty($description)) {
                if ($type === 'youtube') {
                    $description = $this->contentDetection->getYoutubeVideoDescription($url);
                } else {
                    $description = $metadata['description'] ?? null;
                }
            }

            $faviconUrl = $faviconUrl ?: ($metadata['favicon'] ?? null);
            $thumbnailUrl = $thumbnailUrl ?: ($metadata['image'] ?? $metadata['og_image'] ?? null);
        }

        $link = Link::create([
            'user_id' => $user->id,
            'team_id' => $teamId,
            'url' => $url,
            'url_hash' => $urlHash,
            'title' => $title ?: Str::limit($url, 100),
            'description' => $description,
            'content_type' => ContentType::normalize($type, $analysis['type'] ?? 'other'),
            'category_id' => $categoryId,
            'folder_id' => $folderId,
            'favicon_url' => $faviconUrl,
            'thumbnail_url' => $thumbnailUrl,
            'metadata' => $metadata,
            'is_favorite' => (bool) ($data['is_favorite'] ?? false),
            'ai_summary_status' => 'pending',
        ]);

        // Process tags
        if (! empty($data['tags']) && is_array($data['tags'])) {
            $tagIds = [];
            $tagNames = [];

            foreach ($data['tags'] as $tagItem) {
                $tagName = trim((string) $tagItem);
                if (empty($tagName)) {
                    continue;
                }

                $tag = Tag::firstOrCreate([
                    'user_id' => $user->id,
                    'team_id' => $teamId,
                    'slug' => Str::slug($tagName),
                ], [
                    'name' => $tagName,
                ]);

                $tagIds[] = $tag->id;
                $tagNames[] = $tagName;
            }

            if (! empty($tagIds)) {
                $link->tags()->sync($tagIds);
                $link->update(['tags' => implode(', ', $tagNames)]);
            }
        }

        // Trigger AI summary in background queue if requested
        if (! empty($data['generate_ai_summary'])) {
            GenerateLinkAiSummaryJob::dispatch($link);
        }

        $link->load('tags', 'category', 'folder', 'team');
        $tenant = $link->team;
        $webUrl = $tenant
            ? url("/app/{$tenant->slug}/links/{$link->id}")
            : url('/app');

        return response()->json([
            'message' => 'Lien enregistré avec succès dans Links Vault !',
            'link' => $link,
            'vault_url' => $webUrl,
            'is_duplicate' => false,
        ], 201);
    }
}
