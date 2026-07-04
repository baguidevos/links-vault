<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class TagFinderAgent implements Agent, Conversational, HasStructuredOutput, HasTools
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
Tu es un assitant expert en résumé de texte. Tu saisi l'essentiel d'un text et tu en fait le resumé. Ton travail
est de lire des transcription de vidéo youtube ou description de site web et d'en faire faire un résumé concis.

1. Relevant tags (3-5 keywords or short phrases) that would help categorize and find this link


Guidelines:
- Keep tags concise and actionable
- Tags should be lowercase, single words or short phrases
- Be specific with tags - avoid generic terms like "interesting" or "good"
- The output should be an array of tags separated by commas : ['tech','web',...]
- Tags should be relevant to the content of the link
PROMPT;
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return [];
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [];
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'value' => $schema->string()->required(),
        ];
    }
}
