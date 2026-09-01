<?php

declare(strict_types=1);

namespace App\Actions\LinkActions;

use App\Ai\Agents\LinkSummaryAgent;
use App\Models\Link;
use App\Models\Tag;
use App\Services\SubscriptionQuotaService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GenerateAiSummaryAction
{
    /**
     * Analyse et génère un résumé IA pour le lien donné.
     */
    public static function execute(Link $link): Link
    {
        $user = $link->user ?? auth()->user();
        if ($user) {
            $quotaService = app(SubscriptionQuotaService::class);
            if (! $quotaService->canGenerateAiSummary($user)) {
                $link->update([
                    'ai_summary_status' => 'failed',
                ]);
                Log::warning("AI Summary generation skipped: Monthly quota exceeded for user {$user->id}");

                return $link;
            }
        }

        $link->update([
            'ai_summary_status' => 'processing',
        ]);

        try {
            $promptParts = [];
            $promptParts[] = "URL: {$link->url}";
            if ($link->title) {
                $promptParts[] = "Title: {$link->title}";
            }
            if ($link->description) {
                $promptParts[] = "Description: {$link->description}";
            }
            if ($link->objective) {
                $promptParts[] = "User Objective: {$link->objective}";
            }
            if ($link->content_type) {
                $promptParts[] = "Content Type: {$link->content_type->value}";
            }

            // Ajouter les métadonnées OpenGraph supplémentaires si présentes
            if (is_array($link->metadata)) {
                if (! empty($link->metadata['description']) && $link->metadata['description'] !== $link->description) {
                    $promptParts[] = "Page Content Extract: {$link->metadata['description']}";
                }
                if (! empty($link->metadata['author'])) {
                    $promptParts[] = "Author: {$link->metadata['author']}";
                }
            }

            $prompt = implode("\n\n", $promptParts);

            // Exécution de l'agent IA via Laravel AI SDK
            $agent = new LinkSummaryAgent;
            $result = $agent->prompt($prompt);

            $summary = $result['summary'] ?? null;
            $keyTakeaways = $result['key_takeaways'] ?? [];
            $suggestedTags = $result['suggested_tags'] ?? [];
            $suggestedCategory = $result['category'] ?? null;

            // Formater le texte Markdown du résumé
            $formattedMarkdown = '';
            if ($summary) {
                $formattedMarkdown .= $summary;
            }

            if (! empty($keyTakeaways) && is_array($keyTakeaways)) {
                $formattedMarkdown .= "\n\n**Points essentiels :**\n";
                foreach ($keyTakeaways as $takeaway) {
                    $formattedMarkdown .= "- {$takeaway}\n";
                }
            }

            $metadata = is_array($link->metadata) ? $link->metadata : [];
            $metadata['ai_analysis'] = [
                'summary' => $summary,
                'key_takeaways' => $keyTakeaways,
                'suggested_tags' => $suggestedTags,
                'suggested_category' => $suggestedCategory,
                'generated_at' => now()->toIso8601String(),
            ];

            $updateData = [
                'ai_summary' => trim($formattedMarkdown),
                'ai_summary_status' => 'completed',
                'metadata' => $metadata,
            ];

            $link->update($updateData);

            // Si le lien n'avait pas encore de tags définis, synchroniser les tags suggérés
            if ($link->tags()->count() === 0 && ! empty($suggestedTags) && is_array($suggestedTags)) {
                $tagIds = [];
                foreach ($suggestedTags as $tagName) {
                    $cleaned = trim((string) $tagName);
                    if (empty($cleaned)) {
                        continue;
                    }
                    $tag = Tag::firstOrCreate([
                        'user_id' => $link->user_id,
                        'team_id' => $link->team_id,
                        'slug' => Str::slug($cleaned),
                    ], [
                        'name' => $cleaned,
                    ]);
                    $tagIds[] = $tag->id;
                }

                if (! empty($tagIds)) {
                    $link->tags()->sync($tagIds);
                }
            }

            if ($user) {
                app(SubscriptionQuotaService::class)->consumeAiSummary($user);
            }

            return $link;
        } catch (Throwable $e) {
            Log::error('AI Summary Generation Failed for Link ID '.$link->id, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $link->update([
                'ai_summary_status' => 'failed',
            ]);

            return $link;
        }
    }
}
