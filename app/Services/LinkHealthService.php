<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LinkHealthStatus;
use App\Models\Link;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class LinkHealthService
{
    protected const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36 LinksVault/1.0';

    /**
     * Vérifier la disponibilité et l'état HTTP d'un lien individuel.
     *
     * @return array{
     *     link_id: int,
     *     url: string,
     *     status_code: ?int,
     *     health_status: LinkHealthStatus,
     *     error: ?string,
     *     redirect_url: ?string,
     *     checked_at: Carbon
     * }
     */
    public function checkLink(Link $link): array
    {
        $url = trim((string) $link->url);

        if (empty($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            $healthStatus = LinkHealthStatus::Broken;
            $error = 'URL invalide ou mal formée';
            $statusCode = null;
            $redirectUrl = null;

            $link->update([
                'http_status' => $statusCode,
                'health_status' => $healthStatus,
                'last_health_checked_at' => now(),
                'health_error' => $error,
                'redirect_url' => $redirectUrl,
            ]);

            return [
                'link_id' => $link->id,
                'url' => $url,
                'status_code' => $statusCode,
                'health_status' => $healthStatus,
                'error' => $error,
                'redirect_url' => $redirectUrl,
                'checked_at' => now(),
            ];
        }

        $statusCode = null;
        $healthStatus = LinkHealthStatus::Unknown;
        $error = null;
        $redirectUrl = null;

        try {
            // 1. Tenter d'abord une requête HEAD légère
            $response = Http::withUserAgent(self::USER_AGENT)
                ->connectTimeout(3)
                ->timeout(5)
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 5,
                        'strict' => false,
                        'referer' => true,
                        'track_redirects' => true,
                    ],
                    'verify' => false, // Évite les échecs stricts sur certificats auto-signés tout en vérifiant l'accès
                ])
                ->head($url);

            // Si le serveur refuse la méthode HEAD (405) ou renvoie 403 (protection anti-bot sur HEAD), tenter un GET partiel
            if (in_array($response->status(), [405, 403, 400], true)) {
                $response = Http::withUserAgent(self::USER_AGENT)
                    ->withHeaders(['Range' => 'bytes=0-1024'])
                    ->connectTimeout(3)
                    ->timeout(6)
                    ->withOptions([
                        'allow_redirects' => [
                            'max' => 5,
                            'track_redirects' => true,
                        ],
                        'verify' => false,
                    ])
                    ->get($url);
            }

            $statusCode = $response->status();

            // Vérifier s'il y a eu redirection
            $effectiveUrl = $response->effectiveUri();
            if ($effectiveUrl && (string) $effectiveUrl !== $url) {
                $redirectUrl = (string) $effectiveUrl;
            }

            if ($statusCode >= 200 && $statusCode < 300) {
                $healthStatus = LinkHealthStatus::Healthy;
            } elseif ($statusCode >= 300 && $statusCode < 400) {
                $healthStatus = LinkHealthStatus::Redirect;
            } elseif ($statusCode === 404) {
                $healthStatus = LinkHealthStatus::Broken;
                $error = 'Page introuvable (404 Not Found)';
            } elseif ($statusCode === 403) {
                // Si 403 sur un site public, souvent un WAF, mais le serveur répond. On le classe en Broken avec détail
                $healthStatus = LinkHealthStatus::Broken;
                $error = 'Accès interdit (403 Forbidden)';
            } elseif ($statusCode === 410) {
                $healthStatus = LinkHealthStatus::Broken;
                $error = 'Ressource définitivement supprimée (410 Gone)';
            } elseif ($statusCode >= 500) {
                $healthStatus = LinkHealthStatus::Broken;
                $error = "Erreur interne du serveur distant ({$statusCode})";
            } else {
                $healthStatus = LinkHealthStatus::Broken;
                $error = "Code HTTP inattendu ({$statusCode})";
            }
        } catch (ConnectionException $e) {
            $healthStatus = LinkHealthStatus::Broken;
            $statusCode = 0;
            $error = 'Délai d\'attente dépassé ou hôte inaccessible';
            Log::info("Link health check connection error for link {$link->id}: {$e->getMessage()}");
        } catch (RequestException $e) {
            $statusCode = $e->response?->status();
            $healthStatus = LinkHealthStatus::Broken;
            $error = "Échec de requête HTTP: {$e->getMessage()}";
        } catch (Throwable $e) {
            $healthStatus = LinkHealthStatus::Broken;
            $statusCode = null;
            $error = $e->getMessage() ?: 'Erreur réseau inconnue';
            Log::info("Link health check unexpected error for link {$link->id}: {$e->getMessage()}");
        }

        $link->update([
            'http_status' => $statusCode,
            'health_status' => $healthStatus,
            'last_health_checked_at' => now(),
            'health_error' => $error ? mb_substr($error, 0, 500) : null,
            'redirect_url' => $redirectUrl ? mb_substr($redirectUrl, 0, 2083) : null,
        ]);

        return [
            'link_id' => $link->id,
            'url' => $url,
            'status_code' => $statusCode,
            'health_status' => $healthStatus,
            'error' => $error,
            'redirect_url' => $redirectUrl,
            'checked_at' => now(),
        ];
    }

    /**
     * Vérifier tous les liens d'un espace de travail.
     *
     * @param  Team  $team  L'équipe concernée
     * @param  int|null  $limit  Nombre maximum de liens à vérifier
     * @param  bool  $force  Si false, vérifie prioritairement les non-vérifiés ou anciens de plus de 7 jours
     * @return array{total: int, healthy: int, broken: int, redirect: int}
     */
    public function checkTeamLinks(Team $team, ?int $limit = null, bool $force = false): array
    {
        $query = Link::query()->where('team_id', $team->id);

        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('last_health_checked_at')
                    ->orWhere('health_status', 'unknown')
                    ->orWhere('last_health_checked_at', '<', now()->subDays(7));
            });
        }

        if ($limit && $limit > 0) {
            $query->limit($limit);
        }

        $links = $query->get();

        $stats = [
            'total' => $links->count(),
            'healthy' => 0,
            'broken' => 0,
            'redirect' => 0,
        ];

        foreach ($links as $link) {
            $res = $this->checkLink($link);
            if ($res['health_status'] === LinkHealthStatus::Healthy) {
                $stats['healthy']++;
            } elseif ($res['health_status'] === LinkHealthStatus::Broken) {
                $stats['broken']++;
            } elseif ($res['health_status'] === LinkHealthStatus::Redirect) {
                $stats['redirect']++;
            }
        }

        return $stats;
    }

    /**
     * Vérifie une collection de liens.
     *
     * @param  Collection<int, Link>|array<int, Link>  $links
     * @return array{total: int, healthy: int, broken: int, redirect: int}
     */
    public function checkCollection(Collection|array $links): array
    {
        $stats = [
            'total' => count($links),
            'healthy' => 0,
            'broken' => 0,
            'redirect' => 0,
        ];

        foreach ($links as $link) {
            $res = $this->checkLink($link);
            if ($res['health_status'] === LinkHealthStatus::Healthy) {
                $stats['healthy']++;
            } elseif ($res['health_status'] === LinkHealthStatus::Broken) {
                $stats['broken']++;
            } elseif ($res['health_status'] === LinkHealthStatus::Redirect) {
                $stats['redirect']++;
            }
        }

        return $stats;
    }
}
