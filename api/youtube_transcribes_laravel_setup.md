# Intégration de l'API YouTube Transcribes dans Laravel

Ce guide explique comment configurer et utiliser l'API YouTube Transcribes en Laravel, avec pour objectif de récupérer les transcriptions complètes (pour pouvoir générer des résumés). Le code gère avec précision les différentes erreurs documentées en renvoyant des messages adaptés en français.

## 1. Configuration de la clé API

Ajoutez votre clé dans votre fichier `.env` à la racine de votre projet Laravel :
```env
YOUTUBE_TRANSCRIBES_API_KEY=38t4CYOTfOYkx-cXN1CfrPA9BH3YwIENkKsO6zfiWwEpCePxi_8l5kOWHb16l_GV
```

Ensuite, déclarez-la dans le fichier `config/services.php` (généralement à la fin du tableau) :
```php
    'youtube_transcribes' => [
        'key' => env('YOUTUBE_TRANSCRIBES_API_KEY'),
    ],
```

## 2. Le Service Laravel

Créez un dossier `Services` dans `app/` s'il n'existe pas déjà, puis créez le fichier `app/Services/YouTubeTranscriptService.php`.

Ce service s'occupe de :
- Poser la requête avec les bons en-têtes (notamment `X-API-Key`).
- Récupérer les segments du fichier json renvoyé par l'API et les assembler en un seul bloc de texte brut continu.
- Remonter des exceptions avec des messages propres en français pour chaque cas d'erreur de l'API (400, 401, 402, 429).

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use Exception;

class YouTubeTranscriptService
{
    protected string $baseUrl = 'https://youtubetranscribes.com/api/v2';
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.youtube_transcribes.key');
    }

    /**
     * Récupère la transcription complète d'une vidéo YouTube sous forme de texte brut 
     * (idéal pour générer un résumé ensuite).
     *
     * @param string $videoUrl L'URL de la vidéo YouTube (ex: https://www.youtube.com/watch?v=...)
     * @return string Le texte complet de la transcription
     * @throws Exception
     */
    public function fetchTranscriptText(string $videoUrl): string
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->post("{$this->baseUrl}/transcripts/", [
                'url' => $videoUrl,
                'language' => 'auto', // Tente de détecter automatiquement la langue
                'caption_type' => 'auto',
                'download_format' => 'json'
            ]);

            // Si le code HTTP n'est pas 200, on délègue à notre gestionnaire d'erreurs
            if ($response->failed()) {
                $this->handleError($response);
            }

            $data = $response->json();

            // Gestion du statut du job d'après la documentation OpenAPI
            if ($data['status'] === 'completed' && !empty($data['transcripts'])) {
                // Le format 'json' renvoie un tableau d'objets (start, end, text). 
                // On extrait uniquement le texte de chaque segment et on les assemble.
                return collect($data['transcripts'])
                            ->pluck('text')
                            ->implode(' ');
            } elseif ($data['status'] === 'processing') {
                 throw new Exception("La transcription est en cours de traitement. Veuillez réessayer dans quelques instants.");
            } elseif ($data['status'] === 'failed') {
                 throw new Exception("L'extraction de la transcription a échoué. Assurez-vous que la vidéo possède bien des sous-titres.");
            }

            throw new Exception("Statut de réponse inattendu ou aucune transcription trouvée.");

        } catch (RequestException $e) {
            throw new Exception("Erreur de communication avec l'API YouTube Transcribes : " . $e->getMessage());
        }
    }

    /**
     * Traite les différents codes de retour d'erreur HTTP de l'API.
     */
    protected function handleError($response): void
    {
        $status = $response->status();
        
        // L'API renvoie les erreurs sous forme d'objet { "error": { "code": "...", "message": "..." } }
        $errorData = $response->json('error');
        $errorMessage = $errorData['message'] ?? 'Erreur inconnue de la part de l\'API.';
        
        $message = match ($status) {
            400 => "Requête invalide : " . $errorMessage,
            401 => "Erreur d'authentification : Votre clé API YouTube Transcribes est manquante ou invalide.",
            402 => "Crédits insuffisants : Vous n'avez pas assez de crédits sur votre compte YouTube Transcribes.",
            429 => "Limite de requêtes atteinte : Vous faites trop de requêtes simultanées. Veuillez patienter.",
            default => "Erreur serveur inattendue ($status) : " . $errorMessage,
        };

        throw new Exception($message);
    }
}
```

## 3. Exemple d'utilisation dans un Controller

Voici comment exploiter le service dans un Controller pour récupérer le texte continu brut, afin de l'envoyer à votre intégration IA qui s'occupera du résumé.

```php
<?php

namespace App\Http\Controllers;

use App\Services\YouTubeTranscriptService;
use Illuminate\Http\Request;
use Exception;

class SummaryController extends Controller
{
    public function generateSummary(Request $request, YouTubeTranscriptService $transcriptService)
    {
        $request->validate([
            'youtube_url' => 'required|url'
        ]);

        try {
            // 1. On récupère tout le texte de la vidéo (prêt pour un résumé)
            $fullTranscriptText = $transcriptService->fetchTranscriptText($request->youtube_url);

            // 2. Traitement des données récupérées 
            // C'est ici que vous appelez votre IA (OpenAI, Anthropic, etc.) 
            // pour générer le résumé basé sur "$fullTranscriptText"
            $summary = $this->votreFonctionDeResume($fullTranscriptText);

            return response()->json([
                'success' => true,
                'summary' => $summary
            ]);

        } catch (Exception $e) {
            // En cas d'erreur (402 : crédits insuffisants, 401 : erreur d'API, etc.),
            // le message propre écrit dans le service s'affichera ici.
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400); 
        }
    }
}
```
