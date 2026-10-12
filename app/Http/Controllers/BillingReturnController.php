<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SubscriptionPayment;
use App\Services\SasPayService;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BillingReturnController extends Controller
{
    public function __construct(protected SasPayService $sasPayService) {}

    /**
     * Traiter le retour de redirection après checkout SasPay.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $team = $user?->currentTeam ?? $user?->personalTeam();
        $tenantSlug = $team?->slug ?? 'default';

        $redirectUrl = url("/app/{$tenantSlug}/manage-subscription");

        $paymentRecordId = $request->query('payment_id');
        if (! $paymentRecordId) {
            return redirect($redirectUrl);
        }

        $payment = SubscriptionPayment::find($paymentRecordId);
        if (! $payment || ($user && $payment->user_id !== $user->id)) {
            return redirect($redirectUrl);
        }

        // Si le statut est encore PENDING et qu'un ID de transaction SasPay est connu, vérifier l'état réel
        if ($payment->status === 'PENDING' && $payment->payment_id) {
            try {
                $verification = $this->sasPayService->verifyPayment($payment->payment_id);
                if (($verification['status'] ?? null) === 'SUCCESS') {
                    $this->sasPayService->handleTransactionSuccess($verification);
                    $payment->refresh();
                }
            } catch (\Throwable) {
                // Le webhook se chargera de la mise à jour finale
            }
        }

        if ($payment->status === 'SUCCESS') {
            Notification::make()
                ->title(__('Paiement validé avec succès ! 🎉'))
                ->body(__('Votre abonnement :plan est désormais actif.', [
                    'plan' => $payment->plan?->name[app()->getLocale()] ?? $payment->plan?->slug ?? 'Pro',
                ]))
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title(__('Paiement en cours de traitement'))
                ->body(__('Votre paiement est en cours de confirmation par votre opérateur. Vos quotas seront actualisés sous peu.'))
                ->info()
                ->send();
        }

        return redirect($redirectUrl);
    }
}
