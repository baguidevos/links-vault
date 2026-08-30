<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebPageMetadataService
{
    /**
     * Timeout en secondes pour les requêtes HTTP.
     */
    protected const TIMEOUT = 10;

    /**
     * Taille maximale de la réponse (en octets) - 1MB.
     */
    protected const MAX_SIZE = 1048576;

    /**
     * User-Agent pour les requêtes HTTP.
     */
    protected const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * Récupérer les métadonnées d'une page web.
     *
     * @return array{
     *     title: string|null,
     *     description: string|null,
     *     favicon: string|null,
     *     image: string|null,
     *     site_name: string|null,
     *     author: string|null,
     *     type: string|null,
     *     url: string|null,
     *     error: string|null
     * }
     */
    public function fetchMetadata(string $url): array
    {
        // Validation de l'URL
        if (! $this->isValidUrl($url)) {
            return [
                'title' => null,
                'description' => null,
                'favicon' => null,
                'image' => null,
                'site_name' => null,
                'author' => null,
                'type' => null,
                'url' => null,
                'error' => 'URL invalide',
            ];
        }

        $cacheKey = 'web_meta_'.hash('sha256', $url);

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($url) {
            try {
                // Récupérer le contenu HTML
                $html = $this->fetchHtml($url);

                if ($html === null) {
                    return [
                        'title' => null,
                        'description' => null,
                        'favicon' => null,
                        'image' => null,
                        'site_name' => null,
                        'author' => null,
                        'type' => null,
                        'url' => null,
                        'error' => 'Impossible de récupérer le contenu de la page',
                    ];
                }

                // Parser les métadonnées
                $metadata = $this->parseMetadata($html, $url);

                return array_merge($metadata, ['error' => null]);
            } catch (Exception $e) {
                Log::error('Erreur lors de la récupération des métadonnées', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'title' => null,
                    'description' => null,
                    'favicon' => null,
                    'image' => null,
                    'site_name' => null,
                    'author' => null,
                    'type' => null,
                    'url' => null,
                    'error' => $e->getMessage(),
                ];
            }
        });
    }

    /**
     * Récupérer uniquement le favicon d'une page.
     */
    public function fetchFavicon(string $url): ?string
    {
        try {
            $parsedUrl = parse_url($url);
            $scheme = $parsedUrl['scheme'] ?? 'https';
            $host = $parsedUrl['host'] ?? '';

            if (empty($host)) {
                return null;
            }

            // Essayer plusieurs emplacements courants pour le favicon
            $faviconUrls = [
                "{$scheme}://{$host}/favicon.ico",
                "{$scheme}://{$host}/favicon.png",
                "{$scheme}://www.{$host}/favicon.ico",
                "{$scheme}://www.{$host}/favicon.png",
            ];

            foreach ($faviconUrls as $faviconUrl) {
                $response = Http::timeout(5)
                    ->withHeaders(['User-Agent' => self::USER_AGENT])
                    ->withoutVerifying()
                    ->head($faviconUrl);

                if ($response->successful()) {
                    return $faviconUrl;
                }
            }

            // Si aucun favicon trouvé, essayer de parser le HTML
            $html = $this->fetchHtml($url);
            if ($html) {
                $favicon = $this->extractFaviconFromHtml($html, $url);
                if ($favicon) {
                    return $favicon;
                }
            }

            return null;

        } catch (Exception $e) {
            Log::debug('Erreur lors de la récupération du favicon', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Valider si l'URL est correcte.
     */
    protected function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https']);
    }

    /**
     * Récupérer le contenu HTML d'une page.
     */
    protected function fetchHtml(string $url): ?string
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ])
                ->withoutVerifying()
                ->get($url);

            if (! $response->successful()) {
                Log::warning('Échec de la récupération HTML', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return null;
            }

            // Vérifier la taille de la réponse
            $body = $response->body();
            if (strlen($body) > self::MAX_SIZE) {
                // Tronquer à la taille maximale
                $body = substr($body, 0, self::MAX_SIZE);
            }

            return $body;

        } catch (Exception $e) {
            Log::warning('Exception lors de la récupération HTML', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Parser les métadonnées depuis le HTML.
     *
     * @return array{
     *     title: string|null,
     *     description: string|null,
     *     favicon: string|null,
     *     image: string|null,
     *     site_name: string|null,
     *     author: string|null,
     *     type: string|null,
     *     url: string|null
     * }
     */
    protected function parseMetadata(string $html, string $baseUrl): array
    {
        $metadata = [
            'title' => null,
            'description' => null,
            'favicon' => null,
            'image' => null,
            'site_name' => null,
            'author' => null,
            'type' => null,
            'url' => null,
        ];

        // 1. Extraction spécifique YouTube shortDescription (depuis le script ytInitialPlayerResponse avant nettoyage)
        if (str_contains($baseUrl, 'youtube.com') || str_contains($baseUrl, 'youtu.be')) {
            if (preg_match('/"shortDescription":"(.*?)"(?=,"isCrawlable"|,"allowRatings"|,"lengthSeconds")/s', $html, $ytDesc)) {
                $decodedDesc = stripcslashes($ytDesc[1]);
                if (! empty($decodedDesc)) {
                    $metadata['description'] = trim($decodedDesc);
                }
            }
        }

        // 2. Extraction JSON-LD (Schema.org)
        if (preg_match('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $jsonLdMatch)) {
            try {
                $jsonLd = json_decode(trim($jsonLdMatch[1]), true);
                if (is_array($jsonLd)) {
                    if (empty($metadata['description']) && ! empty($jsonLd['description'])) {
                        $metadata['description'] = is_string($jsonLd['description']) ? trim($jsonLd['description']) : null;
                    }
                    if (empty($metadata['title']) && ! empty($jsonLd['headline'] ?? $jsonLd['name'])) {
                        $metadata['title'] = trim($jsonLd['headline'] ?? $jsonLd['name']);
                    }
                    if (empty($metadata['image']) && ! empty($jsonLd['image'])) {
                        $img = is_array($jsonLd['image']) ? ($jsonLd['image']['url'] ?? $jsonLd['image'][0] ?? null) : $jsonLd['image'];
                        if (is_string($img)) {
                            $metadata['image'] = $this->resolveUrl($img, $baseUrl);
                        }
                    }
                }
            } catch (\Throwable) {
                // Ignorer
            }
        }

        // Nettoyer les scripts et styles pour le parsing des balises standard
        $cleanedHtml = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $cleanedHtml = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $cleanedHtml);

        // Extraire le titre
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $cleanedHtml, $matches)) {
            $metadata['title'] = trim(strip_tags($matches[1]));
        }

        // Extraire les meta tags Open Graph, Twitter et standard
        if (preg_match_all('/<meta[^>]+>/i', $cleanedHtml, $metaMatches)) {
            foreach ($metaMatches[0] as $metaTag) {
                // Description (og:description, twitter:description, description, itemprop)
                if (empty($metadata['description'])) {
                    if (preg_match('/property=["\']og:description["\'].*?content=["\'](.*?)["\']/is', $metaTag, $matches)
                        || preg_match('/content=["\'](.*?)["\'].*?property=["\']og:description["\']/is', $metaTag, $matches)
                        || preg_match('/name=["\']description["\'].*?content=["\'](.*?)["\']/is', $metaTag, $matches)
                        || preg_match('/content=["\'](.*?)["\'].*?name=["\']description["\']/is', $metaTag, $matches)
                        || preg_match('/name=["\']twitter:description["\'].*?content=["\'](.*?)["\']/is', $metaTag, $matches)
                        || preg_match('/itemprop=["\']description["\'].*?content=["\'](.*?)["\']/is', $metaTag, $matches)) {
                        $metadata['description'] = trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
                    }
                }

                // Image
                if (empty($metadata['image'])) {
                    if (preg_match('/property=["\']og:image["\'].*?content=["\'](.*?)["\']/i', $metaTag, $matches)
                        || preg_match('/name=["\']twitter:image["\'].*?content=["\'](.*?)["\']/i', $metaTag, $matches)) {
                        $imageUrl = trim($matches[1]);
                        $metadata['image'] = $this->resolveUrl($imageUrl, $baseUrl);
                    }
                }

                // Site name
                if (preg_match('/property=["\']og:site_name["\'].*?content=["\'](.*?)["\']/i', $metaTag, $matches)) {
                    $metadata['site_name'] = trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
                }

                // Type
                if (preg_match('/property=["\']og:type["\'].*?content=["\'](.*?)["\']/i', $metaTag, $matches)) {
                    $metadata['type'] = trim($matches[1]);
                }

                // URL canonique
                if (preg_match('/property=["\']og:url["\'].*?content=["\'](.*?)["\']/i', $metaTag, $matches)) {
                    $metadata['url'] = trim($matches[1]);
                }
            }
        }

        // Extraire l'auteur
        if (preg_match('/<meta[^>]*name=["\']author["\'][^>]*content=["\'](.*?)["\']/i', $cleanedHtml, $matches)
            || preg_match('/<meta[^>]*content=["\'](.*?)["\'][^>]*name=["\']author["\']/i', $cleanedHtml, $matches)) {
            $metadata['author'] = trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
        }

        // Extraire le favicon
        $metadata['favicon'] = $this->extractFaviconFromHtml($cleanedHtml, $baseUrl);

        // Fallback: utiliser le titre OG si pas de titre normal
        if (empty($metadata['title']) && preg_match('/property=["\']og:title["\'].*?content=["\'](.*?)["\']/i', $cleanedHtml, $matches)) {
            $metadata['title'] = trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
        }

        return $metadata;
    }

    /**
     * Extraire le favicon depuis le HTML.
     */
    protected function extractFaviconFromHtml(string $html, string $baseUrl): ?string
    {
        // Chercher les balises link avec rel="icon" ou rel="shortcut icon"
        if (preg_match('/<link[^>]*rel=["\'](icon|shortcut icon)["\'][^>]*href=["\'](.*?)["\']/i', $html, $matches)
            || preg_match('/<link[^>]*href=["\'](.*?)["\'][^>]*rel=["\'](icon|shortcut icon)["\']/i', $html, $matches)) {

            $faviconUrl = trim($matches[2] ?? $matches[1]);

            return $this->resolveUrl($faviconUrl, $baseUrl);
        }

        // Fallback: favicon.ico à la racine
        $parsedUrl = parse_url($baseUrl);
        $scheme = $parsedUrl['scheme'] ?? 'https';
        $host = $parsedUrl['host'] ?? '';

        if (! empty($host)) {
            return "{$scheme}://{$host}/favicon.ico";
        }

        return null;
    }

    /**
     * Résoudre une URL relative en URL absolue.
     */
    protected function resolveUrl(string $url, string $baseUrl): string
    {
        // Si c'est déjà une URL absolue
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        // Si l'URL commence par //
        if (str_starts_with($url, '//')) {
            $parsedBase = parse_url($baseUrl);
            $scheme = $parsedBase['scheme'] ?? 'https';

            return "{$scheme}:{$url}";
        }

        // Si l'URL commence par /
        if (str_starts_with($url, '/')) {
            $parsedBase = parse_url($baseUrl);
            $scheme = $parsedBase['scheme'] ?? 'https';
            $host = $parsedBase['host'] ?? '';

            return "{$scheme}://{$host}{$url}";
        }

        // URL relative
        $parsedBase = parse_url($baseUrl);
        $scheme = $parsedBase['scheme'] ?? 'https';
        $host = $parsedBase['host'] ?? '';
        $path = $parsedBase['path'] ?? '';

        // Obtenir le dossier de base
        $basePath = dirname($path);
        if ($basePath === '\\' || $basePath === '/') {
            $basePath = '';
        }

        return "{$scheme}://{$host}{$basePath}/{$url}";
    }
}
