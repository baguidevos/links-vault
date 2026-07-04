<?php

namespace App\Filament\Resources\Links\Schemas;

use Alaouy\Youtube\Youtube;
use App\Ai\Agents\LinkDescriptionAgent;
use App\Ai\Agents\TagFinderAgent;
use App\Ai\Agents\YoutubeTranscriptSummary;
use App\Enums\ContentType;
use App\Models\Category;
use App\Services\ContentDetectionService;
use App\Services\GoogleClient\YouTube\YouTubeTranscriptCliService;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;

class LinkForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->components(self::getComponents()),
            ]);
    }

    public static function getComponents(): array
    {
        return [
            Grid::make(2)
                ->components([
                    TextInput::make('url')
                        ->label(__('URL'))
                        ->required()
                        ->maxLength(2048)
                        ->autofocus()
                        ->url()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                            if (empty($state)) {
                                return;
                            }

                            $contentDetection = new ContentDetectionService;
                            $analysis = $contentDetection->analyze($state);
                            $type = $analysis['type'];

                            // Auto-detect content type
                            $set('content_type', $type);
                            $set('favicon_url', $analysis['metadata']['favicon']);
                            $set('thumbnail_url', $analysis['metadata']['image'] ?? 'nom disponible');

                            // Auto-generate title if empty
                            $title = $get('title');
                            if (empty($title)) {
                                $set('title', $contentDetection->generateTitleFromUrl($state, $analysis['type']));
                            }

                            if ($type === 'youtube') {
                                $set('description', $contentDetection->getYoutubeVideoDescription($state));
                            } else {
                                $set('title', $analysis['metadata']['title']);
                                $set('description', $analysis['metadata']['description']);
                            }

                            // Auto-fill metadata
                            if (! empty($analysis['metadata'])) {
                                $set('metadata', json_encode($analysis['metadata']));
                            }
                        }),
                    TextInput::make('title')
                        ->label(__('Title'))
                        ->required()
                        ->maxLength(500),
                ]),
            MarkdownEditor::make('description')
                ->label(__('Description'))
                ->afterLabel(
                    Action::make('generate_ai_description')
                        ->label(__('Generate AI Description'))
                        ->icon('heroicon-o-sparkles')
                        ->button()
                        ->color('primary')
                        ->action(function ($state, Set $set, Get $get) {
                            $url = $get('url');
                            $title = $get('title');
                            $contentType = $get('content_type');
                            $existingDescription = $state ?? '';

                            try {
                                // Préparer le contexte pour l'agent
                                $context = "Titre: {$title}\n";
                                $context .= "URL: {$url}\n";
                                $context .= "Type de contenu: {$contentType}\n";

                                if (! empty($existingDescription)) {
                                    $context .= "Description existante: {$existingDescription}\n";
                                }

                                // Récupérer les métadonnées si disponibles
                                $metadata = $get('metadata');
                                if (! empty($metadata) && $metadata !== '{}') {
                                    $context .= "Métadonnées: {$metadata}\n";
                                }

                                // Si c'est YouTube, ajouter des infos supplémentaires
                                if ($contentType === 'youtube') {
                                    try {
                                        $video_id = Youtube::parseVidFromURL($url);
                                        $video_infos = \Alaouy\Youtube\Facades\Youtube::getVideoInfo($video_id);
                                        $context .= "\nInformations YouTube:\n";
                                        $context .= "- Titre vidéo: {$video_infos->snippet->title}\n";
                                        $context .= "- Chaîne: {$video_infos->snippet->channelTitle}\n";
                                        $context .= "- Date de publication: {$video_infos->snippet->publishedAt}\n";

                                        if (! empty($video_infos->snippet->description)) {
                                            $context .= '- Description originale: '.substr($video_infos->snippet->description, 0, 500)."\n";
                                        }
                                    } catch (\Exception $e) {
                                        Log::warning('Impossible de récupérer les infos YouTube', ['error' => $e->getMessage()]);
                                    }
                                }

                                // Générer la description avec l'agent AI
                                $agent = (new LinkDescriptionAgent)->prompt(
                                    "Génère une description enrichie, des tags et une catégorie suggérée basée sur ces informations:\n\n{$context}",
                                    model: 'openai/gpt-oss-120b',
                                    provider: Lab::Groq
                                );

                                $response = $agent->text;



                                $tagsAgent = (new TagFinderAgent())->prompt(
                                    "Génère des tags basés sur ces informations:\n\n{$context}",
                                    model: 'openai/gpt-oss-120b',
                                    provider: Lab::Groq
                                );

                                $response = $tagsAgent->text;

                                Log::info('Description générée', ['description' => $response]);
                               


                                // Parser la réponse de l'agent
                                $description = '';
                                $tags = [];
                                $category = '';

                                // Extraire la description
                                if (preg_match('/\*\*DESCRIPTION :\*\*\s*(.+?)(?=\n\n\*\*TAGS :\*\*|$)/s', $response, $matches)) {
                                    $description = trim($matches[1]);
                                }

                                // Extraire les tags
                                if (preg_match('/\*\*TAGS :\*\*\s*(.+?)(?=\n\n\*\*CATÉGORIE SUGGÉRÉE :\*\*|$)/s', $response, $matches)) {
                                    $tagsString = trim($matches[1]);
                                    $tags = array_map('trim', explode(',', $tagsString));
                                    $tags = array_filter($tags); // Supprimer les éléments vides
                                }

                                // Extraire la catégorie
                                if (preg_match('/\*\*CATÉGORIE SUGGÉRÉE :\*\*\s*(.+?)$/m', $response, $matches)) {
                                    $category = trim($matches[1]);
                                }

                                // Mettre à jour les champs
                                if (! empty($description)) {
                                    $set('description', $description);
                                }

                                // Si des tags ont été générés, les afficher dans un message
                                if (! empty($tags)) {
                                    $tagsList = implode(', ', $tags);
                                    // On pourrait ici mettre à jour un champ tags si on avait un multi-select
                                    // Pour l'instant, on affiche juste un message informatif
                                }

                                // Si une catégorie a été suggérée et qu'il n'y en a pas déjà une
                                if (! empty($category) && empty($get('category_id'))) {
                                    // Chercher si la catégorie existe déjà
                                    $existingCategory = Category::where('name', 'LIKE', "%{$category}%")->first();
                                    if ($existingCategory) {
                                        $set('category_id', $existingCategory->id);
                                    }
                                    // Sinon, on pourrait créer une nouvelle catégorie automatiquement
                                }

                            } catch (\Exception $e) {
                                Log::error('Erreur génération description AI', [
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString(),
                                    'url' => $url,
                                ]);

                                // Laisser la description telle quelle ou afficher un message d'erreur
                            }
                        })
                ),
            Hidden::make('metadata')
                ->default('{}'),
            Grid::make(3)
                ->components([
                    Select::make('content_type')
                        ->label(__('Content Type'))
                        ->options(collect(ContentType::cases())
                            ->mapWithKeys(fn (ContentType $case) => [$case->value => $case->label()])
                            ->toArray())
                        ->default(ContentType::Other->value),
                    Select::make('category_id')
                        ->label(__('Category'))
                        ->options(fn () => Category::pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label(__('Name'))
                                ->required()
                                ->maxLength(255),
                            TextInput::make('slug')
                                ->label(__('Slug'))
                                ->maxLength(255),
                        ])
                        ->createOptionUsing(function (array $data): int {
                            return Category::create($data)->id;
                        }),
                    TextInput::make('objective')
                        ->label(__('Objective'))
                        ->maxLength(255),
                    TextInput::make('thumbnail_url')
                        ->label(__('Thumbnail'))
                        // ->hiddenOn('create')
                        ->readOnly(),
                    TextInput::make('favicon_url')
                        ->label(__('Favicon'))
                        // ->hiddenOn('create')
                        ->readOnly(),
                ]),
            TagsInput::make('tags')
                ->label(__('Tags'))
                ->default(["fzrf",'fzezrge']),
            Grid::make(2)
                ->components([
                    Toggle::make('is_favorite')
                        ->label(__('Favorite'))
                        ->default(false),
                    Toggle::make('is_archived')
                        ->label(__('Archived'))
                        ->default(false),
                ]),
            MarkdownEditor::make('ai_summary')
                ->afterLabel(
                    Action::make('generate_ai_summary')
                        ->label(__('Generate AI Summary'))
                        ->icon('heroicon-o-sparkles')
                        ->button()
                        ->color('primary')
                        ->action(function ($state, Set $set, Get $get) {

                            $contentDetection = app(ContentDetectionService::class);
                            $analysis = $contentDetection->analyze($get('url'));
                            $type = $analysis['type'];
                            $subtitle = '';
                            $lang = 'fr'; // Langue par défaut : français

                            if ($type === 'youtube') {
                                try {
                                    $video_id = Youtube::parseVidFromURL($get('url'));
                                    $video_infos = \Alaouy\Youtube\Facades\Youtube::getVideoInfo($video_id);

                                    // Récupérer la langue de la vidéo et la normaliser
                                    $detectedLang = $video_infos->snippet->defaultLanguage ?? 'fr';

                                    // Normaliser le code de langue (extraire les 2 premières lettres)
                                    $lang = str_contains($detectedLang, '-')
                                        ? explode('-', $detectedLang)[0]
                                        : $detectedLang;

                                    $lang = strtolower($lang);

                                    // Utiliser le nouveau service CLI avec fallback multi-langues
                                    $cliService = new YouTubeTranscriptCliService;

                                    // Récupérer la transcription avec troncature automatique (1000 mots max)
                                    $subtitle = $cliService->getPlainText($video_id, [$lang, 'en'], 1000);

                                    if (! $subtitle) {
                                        // Vérifier si on a des infos sur les langues disponibles
                                        $availableLangs = $cliService->getAvailableLanguages($video_id);

                                        $langList = ! empty($availableLangs)
                                            ? implode(', ', array_column($availableLangs, 'language'))
                                            : 'aucune';

                                        $set('ai_summary', "⚠️ Impossible de récupérer la transcription.\n\n**Langues disponibles :** {$langList}\n\nVérifiez que la vidéo possède des sous-titres activés.");

                                        return;
                                    }

                                    // Générer le résumé avec l'agent AI
                                    $agent = (new YoutubeTranscriptSummary)->prompt(
                                        "Analyse et génère le résumé en français de cette transcription YouTube (langue originale : {$lang}) : {$subtitle}",
                                        model: 'openai/gpt-oss-120b',
                                        provider: Lab::Groq
                                    );

                                    // Ajouter les métadonnées au résumé
                                    $metadata = "**Vidéo :** {$video_infos->snippet->title}\n";
                                    $metadata .= '**Langue détectée :** '.ucfirst($lang);
                                    $metadata .= "\n\n---\n\n";

                                    $set('ai_summary', $metadata.$agent->text);

                                } catch (\Exception $e) {
                                    Log::error('Erreur génération résumé AI', [
                                        'error' => $e->getMessage(),
                                        'trace' => $e->getTraceAsString(),
                                        'url' => $get('url'),
                                        'language' => $lang,
                                    ]);

                                    $set('ai_summary', "❌ **Erreur lors de la génération du résumé**\n\n".$e->getMessage());
                                }
                            } else {
                                $agent = (new YoutubeTranscriptSummary)->prompt(
                                    "Analyse et génère le résumé en français de cette description du lien : {$get('description')}. N'ajoute rien de supperflu, juste ton avis sur la description du line fournis.",
                                    model: 'openai/gpt-oss-120b',
                                    provider: Lab::Groq
                                );
                                $set('ai_summary', $agent->text);
                            }

                        })

                )
                ->label(__('AI Summary'))
                ->columnSpanFull()
                ->helperText(__('AI summary will be generated automatically')),
        ];
    }
}
