<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SasPayService;
use Illuminate\Console\Command;
use Nafiswatsiq\Subbase\Models\Plan;

class CheckSasPayConfigCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'saspay:check-config {--test-checkout : Tester la création d\'une session de checkout}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Vérifier la configuration et la connectivité avec l\'API SasPay';

    /**
     * Execute the console command.
     */
    public function handle(SasPayService $sasPayService): int
    {
        $this->info('🔍 Vérification de la configuration SasPay...');

        $apiKey = config('saspay.api_key');
        $webhookSecret = config('saspay.webhook_secret');
        $baseUrl = config('saspay.base_url');

        $this->table(
            ['Paramètre', 'Valeur / Statut'],
            [
                ['URL de base', $baseUrl ?: 'Non défini'],
                ['Clé API (SASPAY_API_KEY)', $apiKey ? (substr((string) $apiKey, 0, 8).'...'.substr((string) $apiKey, -4)) : '❌ NON CONFIGURÉE'],
                ['Secret Webhook (SASPAY_WEBHOOK_SECRET)', $webhookSecret ? 'Configuré ✓' : '⚠️ NON CONFIGURÉ (requis pour vérifier les webhooks)'],
                ['Webhook Endpoint', url('/api/webhooks/saspay')],
            ]
        );

        if (empty($apiKey)) {
            $this->error('SASPAY_API_KEY est manquante dans votre fichier .env.');

            return self::FAILURE;
        }

        $this->info('🌐 Test de connectivité à l\'API SasPay...');
        $result = $sasPayService->checkConfiguration();

        if (! $result['valid']) {
            $this->warn('⚠️ '.$result['message']);

            return self::FAILURE;
        }

        $this->info('✅ '.$result['message']);

        if ($this->option('test-checkout')) {
            $this->info('🛒 Test de création d\'une session de checkout...');
            $user = User::first();
            $plan = Plan::where('slug', 'pro')->first();

            if (! $user || ! $plan) {
                $this->error('Utilisateur ou plan Pro introuvable.');

                return self::FAILURE;
            }

            $checkoutRes = $sasPayService->createCheckoutSession($user, $plan, 'monthly', 'XOF');
            dump($checkoutRes);
        }

        return self::SUCCESS;
    }
}
