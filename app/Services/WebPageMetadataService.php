<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMXPath;
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
     * Taille maximale de la réponse (en octets) - 2MB.
     */
    protected const MAX_SIZE = 2097152;

    /**
     * User-Agent moderne simulant un navigateur standard pour éviter les blocages.
     */
    protected const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';

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

        // Si en cache et avec un titre valide, renvoyer
        if ($cached = Cache::get($cacheKey)) {
            if (is_array($cached) && ! empty($cached['title'])) {
                return $cached;
            }
        }

        try {
            $html = $this->fetchHtml($url);

            if ($html === null) {
                return [
                    'title' => null,
                    'description' => null,
                    'favicon' => $this->fetchFavicon($url),
                    'image' => null,
                    'site_name' => parse_url($url, PHP_URL_HOST),
                    'author' => null,
                    'type' => null,
                    'url' => $url,
                    'error' => 'Impossible de récupérer le contenu de la page',
                ];
            }

            $metadata = $this->parseMetadata($html, $url);
            $result = array_merge($metadata, ['error' => null]);

            if (! empty($result['title'])) {
                Cache::put($cacheKey, $result, now()->addHours(6));
            }

            return $result;
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
                'url' => $url,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Récupérer le favicon d'une page.
     */
    public function fetchFavicon(string $url): ?string
    {
        try {
            $parsedUrl = parse_url($url);
            $host = $parsedUrl['host'] ?? '';

            if (empty($host)) {
                return null;
            }

            return "https://www.google.com/s2/favicons?domain={$host}&sz=64";
        } catch (Exception) {
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
     * Récupérer le contenu HTML d'une page avec gestion des redirections et headers complets.
     */
    protected function fetchHtml(string $url): ?string
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 5,
                        'strict' => false,
                        'referer' => true,
                        'protocols' => ['http', 'https'],
                    ],
                    'verify' => false,
                ])
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
                    'Upgrade-Insecure-Requests' => '1',
                    'Sec-Fetch-Dest' => 'document',
                    'Sec-Fetch-Mode' => 'navigate',
                    'Sec-Fetch-Site' => 'none',
                    'Sec-Fetch-User' => '?1',
                ])
                ->get($url);

            if (! $response->successful()) {
                Log::warning('Échec de la récupération HTML', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $body = $response->body();
            if (strlen($body) > self::MAX_SIZE) {
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
     * Parser les métadonnées depuis le HTML avec DOMXPath et Schema.org.
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
            'url' => $baseUrl,
        ];

        // 1. YouTube shortDescription
        if (str_contains($baseUrl, 'youtube.com') || str_contains($baseUrl, 'youtu.be')) {
            if (preg_match('/"shortDescription":"(.*?)"(?=,"isCrawlable"|,"allowRatings"|,"lengthSeconds")/s', $html, $ytDesc)) {
                $decodedDesc = stripcslashes($ytDesc[1]);
                if (! empty($decodedDesc)) {
                    $metadata['description'] = trim($decodedDesc);
                }
            }
        }

        // 2. Initialisation DOMDocument & DOMXPath
        $dom = new DOMDocument;
        $libxmlState = libxml_use_internal_errors(true);
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($libxmlState);

        $xpath = new DOMXPath($dom);

        // A. Titre (priorité au tag <title>, puis og:title, twitter:title, <h1>)
        $titleQueries = [
            '//title/text()',
            "//meta[@property='og:title']/@content",
            "//meta[@name='twitter:title']/@content",
            '//h1/text()',
        ];

        foreach ($titleQueries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes && $nodes->length > 0) {
                $text = trim($nodes->item(0)?->nodeValue ?? '');
                if (! empty($text)) {
                    $metadata['title'] = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
                    break;
                }
            }
        }

        // B. Description depuis balises meta
        if (empty($metadata['description'])) {
            $descQueries = [
                "//meta[@property='og:description']/@content",
                "//meta[@name='description']/@content",
                "//meta[@name='twitter:description']/@content",
                "//meta[@itemprop='description']/@content",
            ];

            foreach ($descQueries as $query) {
                $nodes = $xpath->query($query);
                if ($nodes && $nodes->length > 0) {
                    $text = trim($nodes->item(0)?->nodeValue ?? '');
                    if (! empty($text) && strlen($text) > 10) {
                        $metadata['description'] = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
                        break;
                    }
                }
            }
        }

        // C. Image / Miniature
        if (empty($metadata['image'])) {
            $imageQueries = [
                "//meta[@property='og:image:secure_url']/@content",
                "//meta[@property='og:image']/@content",
                "//meta[@name='twitter:image:src']/@content",
                "//meta[@name='twitter:image']/@content",
                "//meta[@itemprop='image']/@content",
                "//link[@rel='image_src']/@href",
            ];

            foreach ($imageQueries as $query) {
                $nodes = $xpath->query($query);
                if ($nodes && $nodes->length > 0) {
                    $src = trim($nodes->item(0)?->nodeValue ?? '');
                    if (! empty($src)) {
                        $metadata['image'] = $this->resolveUrl($src, $baseUrl);
                        break;
                    }
                }
            }
        }

        // D. Favicon
        if (empty($metadata['favicon'])) {
            $iconQueries = [
                "//link[contains(@rel, 'icon')]/@href",
                "//link[contains(@rel, 'shortcut icon')]/@href",
                "//link[contains(@rel, 'apple-touch-icon')]/@href",
            ];

            foreach ($iconQueries as $query) {
                $nodes = $xpath->query($query);
                if ($nodes && $nodes->length > 0) {
                    $iconHref = trim($nodes->item(0)?->nodeValue ?? '');
                    if (! empty($iconHref)) {
                        $metadata['favicon'] = $this->resolveUrl($iconHref, $baseUrl);
                        break;
                    }
                }
            }

            if (empty($metadata['favicon'])) {
                $metadata['favicon'] = $this->fetchFavicon($baseUrl);
            }
        }

        // E. Schema.org JSON-LD (enrichissement supplémentaire si description ou image manquante)
        if (empty($metadata['description']) || empty($metadata['image'])) {
            if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $jsonLdMatches)) {
                foreach ($jsonLdMatches[1] as $jsonString) {
                    try {
                        $jsonLd = json_decode(trim($jsonString), true);
                        if (is_array($jsonLd)) {
                            $item = isset($jsonLd['@graph']) && is_array($jsonLd['@graph']) ? ($jsonLd['@graph'][0] ?? $jsonLd) : $jsonLd;
                            if (is_array($item)) {
                                if (empty($metadata['description']) && ! empty($item['description']) && is_string($item['description'])) {
                                    $metadata['description'] = trim($item['description']);
                                }
                                if (empty($metadata['image']) && ! empty($item['image'])) {
                                    $img = is_array($item['image']) ? ($item['image']['url'] ?? $item['image'][0] ?? null) : $item['image'];
                                    if (is_string($img)) {
                                        $metadata['image'] = $this->resolveUrl($img, $baseUrl);
                                    }
                                }
                            }
                        }
                    } catch (\Throwable) {
                        // Ignorer
                    }
                }
            }
        }

        // F. Fallback description : extraction du premier paragraphe textuel significatif du corps
        if (empty($metadata['description'])) {
            $paragraphs = $xpath->query("//div[contains(@id, 'mw-content-text') or contains(@id, 'content') or contains(@class, 'content') or contains(@class, 'article') or contains(@class, 'post')]//p[not(@class) or contains(@class, 'lead') or contains(@class, 'summary')] | //article//p | //main//p | //p");
            if ($paragraphs) {
                foreach ($paragraphs as $p) {
                    $pText = trim(strip_tags($p->textContent ?? ''));
                    if (strlen($pText) > 40 && ! str_contains($pText, 'modifier le code') && ! str_starts_with($pText, 'Pour les articles')) {
                        $metadata['description'] = html_entity_decode(substr($pText, 0, 350), ENT_QUOTES, 'UTF-8');
                        break;
                    }
                }
            }
        }

        // G. Nom du site
        if (empty($metadata['site_name'])) {
            $nodes = $xpath->query("//meta[@property='og:site_name']/@content");
            if ($nodes && $nodes->length > 0) {
                $metadata['site_name'] = trim($nodes->item(0)?->nodeValue ?? '');
            } else {
                $metadata['site_name'] = parse_url($baseUrl, PHP_URL_HOST);
            }
        }

        // H. Auteur
        if (empty($metadata['author'])) {
            $authorQueries = [
                "//meta[@name='author']/@content",
                "//meta[@property='article:author']/@content",
                "//meta[@name='twitter:creator']/@content",
            ];

            foreach ($authorQueries as $query) {
                $nodes = $xpath->query($query);
                if ($nodes && $nodes->length > 0) {
                    $author = trim($nodes->item(0)?->nodeValue ?? '');
                    if (! empty($author)) {
                        $metadata['author'] = html_entity_decode($author, ENT_QUOTES, 'UTF-8');
                        break;
                    }
                }
            }
        }

        return $metadata;
    }

    /**
     * Résoudre une URL relative en URL absolue.
     */
    protected function resolveUrl(string $url, string $baseUrl): string
    {
        if (empty($url)) {
            return $url;
        }

        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';

            return "{$scheme}:{$url}";
        }

        $parsedBase = parse_url($baseUrl);
        $scheme = $parsedBase['scheme'] ?? 'https';
        $host = $parsedBase['host'] ?? '';

        if (str_starts_with($url, '/')) {
            return "{$scheme}://{$host}{$url}";
        }

        $path = $parsedBase['path'] ?? '';
        $basePath = dirname($path);
        if ($basePath === '\\' || $basePath === '/') {
            $basePath = '';
        }

        return "{$scheme}://{$host}{$basePath}/{$url}";
    }
}
