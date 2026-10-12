<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Services\SasPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SasPayWebhookController extends Controller
{
    public function __construct(protected SasPayService $sasPayService) {}

    /**
     * Traiter les événements envoyés par le webhook SasPay.
     */
    public function handle(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $signature = $request->header('X-Webhook-Signature');
        $timestamp = $request->header('X-Webhook-Timestamp');
        $event = $request->header('X-Webhook-Event') ?? $request->input('event');

        // 1. Vérification de la signature HMAC et de la tolérance d'horodatage (< 300s)
        if (! $this->sasPayService->verifyWebhookSignature($rawBody, $signature, $timestamp)) {
            Log::warning('Tentative d\'accès Webhook SasPay avec signature invalide ou expirée.', [
                'ip' => $request->ip(),
                'signature' => $signature,
                'timestamp' => $timestamp,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Signature invalide ou horodatage expiré.',
            ], 400);
        }

        $payload = $request->json()->all();
        $event = $payload['event'] ?? $event;
        $data = $payload['data'] ?? [];

        Log::info("Webhook SasPay validé avec succès : {$event}", [
            'event' => $event,
            'id' => $data['id'] ?? null,
            'reference' => $data['reference'] ?? null,
        ]);

        // 2. Traitement selon l'événement
        return match ($event) {
            'webhook.test' => response()->json([
                'success' => true,
                'message' => 'Événement de test reçu et validé avec succès !',
            ], 200),

            'transaction.success' => $this->handleTransactionSuccess($data),

            'transaction.failed', 'transaction.cancelled' => $this->handleTransactionFailed($data),

            default => response()->json([
                'success' => true,
                'message' => "Événement '{$event}' acquitté sans action requise.",
            ], 200),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleTransactionSuccess(array $data): JsonResponse
    {
        try {
            $payment = $this->sasPayService->handleTransactionSuccess($data);

            return response()->json([
                'success' => true,
                'payment_id' => $payment?->id,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Erreur lors du traitement de transaction.success : '.$e->getMessage(), [
                'data' => $data,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur interne lors du traitement.',
            ], 500);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleTransactionFailed(array $data): JsonResponse
    {
        $transactionId = $data['id'] ?? null;
        if ($transactionId) {
            SubscriptionPayment::where('payment_id', $transactionId)
                ->where('status', 'PENDING')
                ->update(['status' => 'FAILED']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Transaction marquée comme échouée.',
        ], 200);
    }
}
