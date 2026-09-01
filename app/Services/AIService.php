<?php

namespace App\Services;

use App\Agents\LinkSummaryAgent;
use App\Models\Link;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Facades\Ai;

class AIService
{
    /**
     * Cache TTL for AI suggestions in seconds (30 minutes).
     */
    protected const SUGGESTION_CACHE_TTL = 1800;

    public function __construct(
        protected ContentDetectionService $contentDetection,
    ) {}

    /**
     * Generate an AI summary for a link using the LinkSummaryAgent.
     */
    public function generateSummary(Link $link): array
    {
        $link->update(['ai_summary_status' => 'processing']);

        try {
            $agent = new LinkSummaryAgent($link->user);

            // Build the prompt from link data
            $prompt = $this->buildSummaryPrompt($link);

            $result = $agent->prompt($prompt);
            $output = $result->toArray();

            $link->update([
                'ai_summary' => $output['summary'] ?? null,
                'ai_summary_status' => 'completed',
            ]);

            // Auto-suggest and attach tags
            if (! empty($output['tags']) && is_array($output['tags'])) {
                $this->suggestAndAttachTags($link, $output['tags']);
            }

            // Auto-suggest category
            if (! empty($output['category']) && ! $link->category_id) {
                $this->suggestAndAssignCategory($link, $output['category']);
            }

            return $output;
        } catch (\Exception $e) {
            Log::error('Failed to generate AI summary for link', [
                'link_id' => $link->id,
                'error' => $e->getMessage(),
            ]);

            $link->update(['ai_summary_status' => 'failed']);

            return [];
        }
    }

    /**
     * Generate a summary for a link (alias for generateSummary).
     */
    public function summarize(Link $link): array
    {
        return $this->generateSummary($link);
    }

    /**
     * Suggest tags for a link based on its content.
     *
     * @return array<int, string>
     */
    public function suggestTags(Link $link): array
    {
        $cacheKey = "ai.tag_suggestions.{$link->id}";

        return Cache::remember(
            $cacheKey,
            self::SUGGESTION_CACHE_TTL,
            fn () => $this->fetchTagSuggestions($link)
        );
    }

    /**
     * Suggest a category for a link.
     */
    public function suggestCategory(Link $link): ?string
    {
        $cacheKey = "ai.category_suggestion.{$link->id}";

        return Cache::remember(
            $cacheKey,
            self::SUGGESTION_CACHE_TTL,
            fn () => $this->fetchCategorySuggestion($link)
        );
    }

    /**
     * Get tags for a user sorted by usage count.
     *
     * @return Collection<int, array{slug: string, name: string, count: int}>
     */
    public function getPopularTagsForUser(User $user, int $limit = 20): Collection
    {
        return $user->links()
            ->join('link_tag', 'links.id', '=', 'link_tag.link_id')
            ->join('tags', 'tags.id', '=', 'link_tag.tag_id')
            ->selectRaw('tags.slug, tags.name, COUNT(*) as count')
            ->groupBy('tags.id', 'tags.slug', 'tags.name')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($tag) => [
                'slug' => $tag->slug,
                'name' => $tag->name,
                'count' => (int) $tag->count,
            ]);
    }

    /**
     * Build the prompt text for the AI summary agent.
     */
    protected function buildSummaryPrompt(Link $link): string
    {
        $parts = [];

        $parts[] = 'Analyze this saved link and provide a concise summary and relevant tags.';
        $parts[] = '';
        $parts[] = "**URL:** {$link->url}";

        if ($link->title) {
            $parts[] = "**Title:** {$link->title}";
        }

        if ($link->description) {
            $parts[] = "**Description:** {$link->description}";
        }

        if ($link->content_type !== 'other') {
            $parts[] = "**Content Type:** {$link->content_type}";
        }

        $metadata = $link->metadata;
        if (! empty($metadata)) {
            $parts[] = '**Metadata:** '.json_encode($metadata);
        }

        if ($link->objective) {
            $parts[] = "**User's Objective:** {$link->objective}";
        }

        $parts[] = '';
        $parts[] = 'Please provide:';
        $parts[] = '1. A concise summary (2-3 sentences) of what this link is about';
        $parts[] = '2. An array of 3-5 relevant tags (lowercase, single words or short phrases)';
        $parts[] = '3. A suggested category name if applicable';

        return implode("\n", $parts);
    }

    /**
     * Fetch tag suggestions using AI.
     *
     * @return array<int, string>
     */
    protected function fetchTagSuggestions(Link $link): array
    {
        try {
            $prompt = "Based on this link, suggest 5 relevant tags as a JSON array of strings.\n";
            $prompt .= "URL: {$link->url}\n";

            if ($link->title) {
                $prompt .= "Title: {$link->title}\n";
            }

            if ($link->content_type !== 'other') {
                $prompt .= "Type: {$link->content_type}\n";
            }

            $prompt .= 'Respond with ONLY a JSON array, e.g. ["tech", "programming", "tutorial"]';

            $response = Http::timeout(15)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post(config('ai.providers.openai.url', 'https://api.openai.com/v1/chat/completions'), [
                    'model' => config('ai.providers.openai.model', 'gpt-4o-mini'),
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a helpful tag suggestion assistant. Respond with ONLY a JSON array of tag strings.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 200,
                ]);

            if ($response->successful()) {
                $body = $response->json();
                $content = $body['choices'][0]['message']['content'] ?? '';
                $content = trim($content);

                // Try to parse JSON array
                if (str_starts_with($content, '[')) {
                    $tags = json_decode($content, true);

                    if (is_array($tags)) {
                        return array_map('strtolower', array_map('trim', $tags));
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch AI tag suggestions', [
                'link_id' => $link->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback: generate basic tags from URL
        return $this->generateFallbackTags($link);
    }

    /**
     * Fetch category suggestion using AI.
     */
    protected function fetchCategorySuggestion(Link $link): ?string
    {
        try {
            $prompt = "Based on this link, suggest ONE category name.\n";
            $prompt .= "URL: {$link->url}\n";

            if ($link->title) {
                $prompt .= "Title: {$link->title}\n";
            }

            $prompt .= 'Respond with ONLY the category name, e.g. Technology';

            $response = Http::timeout(15)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post(config('ai.providers.openai.url', 'https://api.openai.com/v1/chat/completions'), [
                    'model' => config('ai.providers.openai.model', 'gpt-4o-mini'),
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a helpful category suggestion assistant. Respond with ONLY a single category name.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.5,
                    'max_tokens' => 50,
                ]);

            if ($response->successful()) {
                $body = $response->json();
                $category = trim($body['choices'][0]['message']['content'] ?? '');

                return $category !== '' ? $category : null;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch AI category suggestion', [
                'link_id' => $link->id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Generate fallback tags from URL content without AI.
     *
     * @return array<int, string>
     */
    protected function generateFallbackTags(Link $link): array
    {
        $tags = [];

        // Tags from content type
        if ($link->content_type !== 'other') {
            $tags[] = $link->content_type;
        }

        // Tags from domain
        $domain = parse_url($link->url, PHP_URL_HOST) ?? '';
        $domain = str_replace(['www.', '.com', '.org', '.net'], '', $domain);

        if ($domain && strlen($domain) > 2) {
            $tags[] = Str::slug($domain);
        }

        // Tags from title
        if ($link->title) {
            $words = preg_split('/[\s\-_]+/', strtolower($link->title));
            $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'is', 'it', 'this', 'that'];
            $significant = array_values(array_filter($words, fn ($w) => strlen($w) > 3 && ! in_array($w, $stopWords, true)));
            $tags = array_merge($tags, array_slice($significant, 0, 3));
        }

        return array_unique(array_filter($tags));
    }

    /**
     * Suggest tags and attach them to a link.
     *
     * @param  array<int, string>  $suggestedTags
     */
    protected function suggestAndAttachTags(Link $link, array $suggestedTags): void
    {
        $existingTags = $link->tags()->pluck('slug')->all();
        $tagsToAttach = [];

        foreach ($suggestedTags as $tagName) {
            $tagName = strtolower(trim($tagName));
            $slug = Str::slug($tagName);

            // Skip if already attached
            if (in_array($slug, $existingTags, true)) {
                continue;
            }

            $tag = Tag::firstOrCreate(
                ['slug' => $slug],
                ['name' => $tagName]
            );

            $tagsToAttach[] = $tag->id;
        }

        if (! empty($tagsToAttach)) {
            $link->tags()->attach($tagsToAttach);
        }
    }

    /**
     * Suggest a category and assign it to a link if none exists.
     */
    protected function suggestAndAssignCategory(Link $link, string $suggestedCategory): void
    {
        if ($link->category_id) {
            return;
        }

        $slug = Str::slug($suggestedCategory);
        $category = $link->user->categories()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $suggestedCategory]
        );

        $link->update(['category_id' => $category->id]);
    }
}
