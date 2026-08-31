<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class LinkSummaryAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Instructions fournies à l'agent IA.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You are an expert content analysis and summarization assistant for LinksVault, an intelligent knowledge and link management platform.
Your goal is to analyze the provided web page, article, video, or document information and generate:
1. A concise, crystal-clear TL;DR summary (2 to 3 sentences) highlighting what the content is about and why it is valuable.
2. 3 actionable key takeaways or main insights.
3. 3 to 5 specific, high-quality tags in lowercase (e.g., "laravel", "vuejs", "tailwind-css", "ui-ux", "machine-learning").
4. A recommended category name (e.g., "Technology", "Design", "Artificial Intelligence", "Business", "Marketing", "Tutorial", "Productivity").
PROMPT;
    }

    /**
     * Schéma de sortie structuré pour garantir un formatage JSON strict.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()
                ->description('A concise 2-3 sentence summary of what the link is about and its core value')
                ->required(),
            'key_takeaways' => $schema->array()
                ->items($schema->string())
                ->description('3 bullet points highlighting key insights or takeaways')
                ->required(),
            'suggested_tags' => $schema->array()
                ->items($schema->string())
                ->description('3 to 5 lowercase keyword tags')
                ->required(),
            'category' => $schema->string()
                ->description('A suggested category name for this link')
                ->nullable(),
        ];
    }
}
