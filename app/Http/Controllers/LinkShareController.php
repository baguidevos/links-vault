<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LinkShare;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class LinkShareController extends Controller
{
    /**
     * Rediriger vers le lien original et tracker le clic.
     */
    public function redirect(Request $request, string $token)
    {
        try {
            // Trouver le partage par token
            $share = LinkShare::with(['link', 'sender', 'recipient'])
                ->where('token', $token)
                ->first();

            if (! $share) {
                abort(404, 'Lien de partage introuvable ou expiré.');
            }

            // Vérifier si le partage est expiré
            if ($share->isExpired()) {
                Log::warning('Tentative d\'accès à un partage expiré', [
                    'token' => $token,
                    'link_id' => $share->link_id,
                ]);

                return response()->view('errors.link-expired', [
                    'share' => $share,
                ], 410);
            }

            // Marquer comme ouvert (si pas déjà fait)
            if (! $share->opened_at) {
                $share->markAsOpened();
            }

            // Marquer comme cliqué
            $share->markAsClicked();

            // Incrémenter le compteur de visites du lien
            if ($share->link) {
                $share->link->increment('visit_count');
                $share->link->update(['last_visited_at' => now()]);
            }

            // Rediriger vers l'URL originale
            return redirect()->to($share->link?->url ?? '/');

        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Erreur lors du tracking de partage', [
                'token' => $token,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            abort(500, 'Une erreur est survenue lors du traitement de votre demande.');
        }
    }
}
