<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SubscriptionQuotaService;
use Illuminate\Console\Command;
use Laravelcm\Subscriptions\Models\Subscription;
use Nafiswatsiq\Subbase\Models\Plan;

class CheckSubscriptionExpirationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:check-expirations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Vérifier et rétrograder les abonnements expirés vers le plan gratuit';

    /**
     * Execute the console command.
     */
    public function handle(SubscriptionQuotaService $quotaService): int
    {
        $this->info('🕒 Vérification des abonnements expirés...');

        $freePlan = Plan::where('slug', 'free')->first();
        if (! $freePlan) {
            $this->error('Plan gratuit (free) introuvable en base.');

            return self::FAILURE;
        }

        // Récupérer les abonnements payants arrivés à échéance (ends_at dépassé)
        $expiredSubscriptions = Subscription::query()
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->whereHas('plan', fn ($q) => $q->where('slug', '!=', 'free'))
            ->with(['subscriber', 'plan'])
            ->get();

        if ($expiredSubscriptions->isEmpty()) {
            $this->info('✅ Aucun abonnement expiré trouvé.');

            return self::SUCCESS;
        }

        $this->info("🔄 Traitement de {$expiredSubscriptions->count()} abonnement(s) expiré(s)...");

        foreach ($expiredSubscriptions as $sub) {
            $user = $sub->subscriber;
            if (! $user) {
                continue;
            }

            $currentPlanName = $sub->plan?->slug ?? 'inconnu';
            $this->line("  - Utilisateur #{$user->id} ({$user->email}) : fin de l'offre '{$currentPlanName}'.");

            $quotaService->subscribeToPlan($user, $freePlan);
            $this->info('    ↳ Rétrogradé avec succès sur le plan Gratuit.');
        }

        $this->info('Terminé avec succès.');

        return self::SUCCESS;
    }
}
