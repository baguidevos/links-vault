<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Nafiswatsiq\Subbase\Models\Plan;
use RuntimeException;

class SasPayService
{
    protected string $apiKey;

    protected string $webhookSecret;

    protected string $baseUrl;

    protected int $toleranceSeconds;

    public function __construct(protected SubscriptionQuotaService $quotaService)
    {
        $this->apiKey = (string) config('saspay.api_key', '');
        $this->webhookSecret = (string) config('saspay.webhook_secret', '');
        $this->baseUrl = rtrim((string) config('saspay.base_url', 'https://api.saspay.me/api/v1'), '/');
        $this->toleranceSeconds = (int) config('saspay.tolerance_seconds', 300);
    }

    /**
     * Créer une session de checkout hébergée SasPay pour un abonnement.
     *
     * @return array{success: bool, checkout_url: ?string, payment: ?SubscriptionPayment, error: ?string}
     */
    public function createCheckoutSession(User $user, Plan $plan, string $period = 'monthly', string $currency = 'XOF', ?string $returnUrl = null): array
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException(__('La clé API SasPay (SASPAY_API_KEY) n\'est pas configurée.'));
        }

        $period = in_array($period, ['monthly', 'yearly'], true) ? $period : 'monthly';
        $currency = in_array($currency, ['XOF', 'EUR', 'USD'], true) ? $currency : 'XOF';

        // 1. Calculer le montant décimal
        $prices = is_array($plan->prices) ? $plan->prices : [];
        $basePrice = (float) ($prices[$currency] ?? $plan->price ?? 0.0);

        if ($period === 'yearly') {
            // 10 mois facturés (2 mois offerts)
            $totalAmount = $basePrice * 10;
        } else {
            $totalAmount = $basePrice;
        }

        // Montant formaté obligatoirement en chaîne décimale (ex: "3000.00")
        $amountString = number_format($totalAmount, 2, '.', '');

        // 2. Préparer l'enregistrement de paiement PENDING
        $payment = SubscriptionPayment::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'period' => $period,
            'amount' => $amountString,
            'currency' => $currency,
            'status' => 'PENDING',
            'metadata' => [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'plan_slug' => $plan->slug,
                'period' => $period,
            ],
        ]);

        $defaultReturnUrl = route('billing.return', ['payment_id' => $payment->id]);
        $finalReturnUrl = $returnUrl ?? $defaultReturnUrl;

        $planName = $plan->name[app()->getLocale()] ?? $plan->name['fr'] ?? $plan->name['en'] ?? $plan->slug;
        $periodLabel = $period === 'yearly' ? __('annuel') : __('mensuel');
        $description = __('Abonnement LinksVault :plan (:period)', ['plan' => $planName, 'period' => $periodLabel]);

        $payload = [
            'amount' => $amountString,
            'currency' => $currency,
            'description' => substr($description, 0, 150),
            'customer_email' => $user->email,
            'customer_name' => $user->name ?: 'Client LinksVault',
            'return_url' => $finalReturnUrl,
            'metadata' => [
                'payment_record_id' => $payment->id,
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'plan_slug' => $plan->slug,
                'period' => $period,
            ],
        ];

        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->post("{$this->baseUrl}/checkout-sessions/", $payload);

            if ($response->status() === 201 || $response->status() === 200) {
                $raw = $response->json() ?? [];
                $data = $raw['data'] ?? $raw;
                $checkoutUrl = $data['checkout_url'] ?? null;
                $sessionId = $data['id'] ?? null;
                $sessionSlug = $data['slug'] ?? null;

                $payment->update([
                    'session_id' => $sessionId,
                    'session_slug' => $sessionSlug,
                    'checkout_url' => $checkoutUrl,
                ]);

                return [
                    'success' => true,
                    'checkout_url' => $checkoutUrl,
                    'payment' => $payment,
                    'error' => null,
                ];
            }

            $errorMessage = $this->parseErrorMessage($response);
            $payment->update(['status' => 'FAILED']);

            Log::error('Erreur lors de la création de checkout session SasPay', [
                'status' => $response->status(),
                'response' => $response->json(),
                'payload' => $payload,
            ]);

            return [
                'success' => false,
                'checkout_url' => null,
                'payment' => $payment,
                'error' => $errorMessage,
            ];
        } catch (\Throwable $e) {
            $payment->update(['status' => 'FAILED']);
            Log::error('Exception réseau SasPay createCheckoutSession: '.$e->getMessage());

            return [
                'success' => false,
                'checkout_url' => null,
                'payment' => $payment,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Vérifier le statut réel d'un paiement auprès de SasPay.
     *
     * @return array<string, mixed>
     */
    public function verifyPayment(string $paymentId): array
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException(__('La clé API SasPay n\'est pas configurée.'));
        }

        $response = Http::withToken($this->apiKey)
            ->acceptJson()
            ->get("{$this->baseUrl}/payments/{$paymentId}/verify/");

        if ($response->successful()) {
            return $response->json();
        }

        throw new RuntimeException(__('Erreur lors de la vérification du paiement SasPay: :err', [
            'err' => $this->parseErrorMessage($response),
        ]));
    }

    /**
     * Vérifier l'authenticité de la signature HMAC du Webhook SasPay.
     */
    public function verifyWebhookSignature(string $rawBody, ?string $signature, ?string $timestamp): bool
    {
        if (empty($this->webhookSecret) || empty($signature) || empty($timestamp)) {
            return false;
        }

        // 1. Contrôle d'âge : tolérance maximale de 300 secondes (5 minutes)
        if (abs(time() - (int) $timestamp) > $this->toleranceSeconds) {
            Log::warning('Webhook SasPay rejeté : décalage d\'horodatage supérieur à la tolérance', [
                'server_time' => time(),
                'timestamp' => $timestamp,
            ]);

            return false;
        }

        // 2. Calcul HMAC SHA-256 sur timestamp.rawBody
        $signedData = "{$timestamp}.{$rawBody}";
        $expectedSignature = hash_hmac('sha256', $signedData, $this->webhookSecret);

        return hash_equals(strtolower($expectedSignature), strtolower($signature));
    }

    /**
     * Traiter un événement transaction.success de manière idempotente.
     *
     * @param  array<string, mixed>  $data
     */
    public function handleTransactionSuccess(array $data): ?SubscriptionPayment
    {
        $transactionId = $data['id'] ?? null;
        $reference = $data['reference'] ?? null;
        $metadata = $data['metadata'] ?? [];

        // Recherche du paiement correspondant par ID transaction, metadata record id ou reference
        $payment = null;

        if (! empty($metadata['payment_record_id'])) {
            $payment = SubscriptionPayment::find($metadata['payment_record_id']);
        }

        if (! $payment && ! empty($transactionId)) {
            $payment = SubscriptionPayment::where('payment_id', $transactionId)->first();
        }

        if (! $payment && ! empty($reference)) {
            $payment = SubscriptionPayment::where('reference', $reference)->first();
        }

        if (! $payment) {
            // Création d'un enregistrement à la volée si utilisateur retrouvé
            $userId = $metadata['user_id'] ?? null;
            $planId = $metadata['plan_id'] ?? null;

            if ($userId && $planId) {
                $payment = SubscriptionPayment::create([
                    'user_id' => $userId,
                    'plan_id' => $planId,
                    'period' => $metadata['period'] ?? 'monthly',
                    'payment_id' => $transactionId,
                    'reference' => $reference,
                    'amount' => $data['amount'] ?? '0.00',
                    'currency' => $data['currency'] ?? 'XOF',
                    'charged' => $data['charged'] ?? null,
                    'net_amount' => $data['net_amount'] ?? null,
                    'status' => 'PENDING',
                ]);
            }
        }

        if (! $payment) {
            Log::warning('Webhook SasPay reçu pour une transaction sans paiement rattaché', $data);

            return null;
        }

        // Idempotence : Si déjà marqué SUCCESS, ne pas réactiver inutilement
        if ($payment->status === 'SUCCESS') {
            return $payment;
        }

        $payment->update([
            'payment_id' => $transactionId,
            'reference' => $reference,
            'charged' => $data['charged'] ?? null,
            'net_amount' => $data['net_amount'] ?? null,
            'status' => 'SUCCESS',
            'paid_at' => now(),
        ]);

        // Activation de l'abonnement pour l'utilisateur
        $user = $payment->user;
        $plan = $payment->plan;

        if ($user && $plan) {
            $subscription = $this->quotaService->subscribeToPlan($user, $plan);

            // Ajustement de la date d'échéance selon la période
            $endsAt = $payment->period === 'yearly'
                ? now()->addYear()
                : now()->addMonth();

            $subscription->update([
                'ends_at' => $endsAt,
                'trial_ends_at' => null, // Fin de la période d'essai dès le paiement effectué
            ]);

            Log::info("Abonnement activé avec succès pour l'utilisateur #{$user->id} sur le plan {$plan->slug}", [
                'ends_at' => $endsAt->toIso8601String(),
                'payment_id' => $payment->id,
            ]);
        }

        return $payment;
    }

    /**
     * Vérifier l'accès à l'API et la validité de la clé.
     *
     * @return array{configured: bool, valid: bool, message: string}
     */
    public function checkConfiguration(): array
    {
        if (empty($this->apiKey)) {
            return [
                'configured' => false,
                'valid' => false,
                'message' => 'SASPAY_API_KEY est manquante dans le fichier .env.',
            ];
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->get("{$this->baseUrl}/pricing/my-rates/");

            if ($response->successful()) {
                return [
                    'configured' => true,
                    'valid' => true,
                    'message' => 'Connexion réussie à l\'API SasPay.',
                ];
            }

            return [
                'configured' => true,
                'valid' => false,
                'message' => 'Erreur API: '.$this->parseErrorMessage($response),
            ];
        } catch (\Throwable $e) {
            return [
                'configured' => true,
                'valid' => false,
                'message' => 'Exception de connexion: '.$e->getMessage(),
            ];
        }
    }

    protected function parseErrorMessage(Response $response): string
    {
        $json = $response->json();
        if (isset($json['error']['message'])) {
            return (string) $json['error']['message'];
        }
        if (isset($json['message'])) {
            return (string) $json['message'];
        }

        return 'HTTP Error '.$response->status();
    }
}
