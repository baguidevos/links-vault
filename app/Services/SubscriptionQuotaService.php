<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Link;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Laravelcm\Subscriptions\Models\Subscription;
use Nafiswatsiq\Subbase\Models\Plan;

class SubscriptionQuotaService
{
    /**
     * Obtenir ou initialiser l'abonnement actif de l'utilisateur (Free par défaut).
     */
    public function getOrCreateSubscription(User $user): Subscription
    {
        $user->unsetRelation('planSubscriptions');
        $subscription = $user->activePlanSubscriptions()->first();

        if (! $subscription) {
            $freePlan = Plan::where('slug', 'free')->first() ?? Plan::first();
            if (! $freePlan) {
                $freePlan = Plan::create([
                    'slug' => 'free',
                    'name' => ['fr' => 'Gratuit', 'en' => 'Free'],
                    'description' => ['fr' => 'Plan gratuit par défaut', 'en' => 'Default free plan'],
                    'is_active' => true,
                    'price' => 0.00,
                    'signup_fee' => 0.00,
                    'currency' => 'EUR',
                    'trial_period' => 0,
                    'trial_interval' => 'day',
                    'invoice_period' => 1,
                    'invoice_interval' => 'month',
                    'sort_order' => 1,
                ]);
                $freePlan->features()->createMany([
                    ['name' => ['fr' => 'Limite de liens', 'en' => 'Links Limit'], 'slug' => 'links_limit', 'value' => '100', 'resettable_period' => 0],
                    ['name' => ['fr' => 'Résumés IA / mois', 'en' => 'AI Summaries / month'], 'slug' => 'ai_summaries_monthly', 'value' => '10', 'resettable_period' => 1, 'resettable_interval' => 'month'],
                    ['name' => ['fr' => 'Sauvegarde Google Drive Cloud', 'en' => 'Google Drive Cloud Backup'], 'slug' => 'cloud_backup', 'value' => 'false', 'resettable_period' => 0],
                    ['name' => ['fr' => 'Membres d\'équipe', 'en' => 'Team Members'], 'slug' => 'team_members_limit', 'value' => '1', 'resettable_period' => 0],
                    ['name' => ['fr' => 'Scan de santé automatique', 'en' => 'Automated Link Health Scan'], 'slug' => 'link_health_automated', 'value' => 'false', 'resettable_period' => 0],
                ]);
            }
            $subscription = $user->newPlanSubscription('main', $freePlan);
        }

        if ($subscription) {
            $subscription->unsetRelation('plan');
            $subscription->load('plan.features');
        }

        return $subscription;
    }

    /**
     * Obtenir le plan actif actuel.
     */
    public function getCurrentPlan(User $user): Plan
    {
        $subscription = $this->getOrCreateSubscription($user);

        return $subscription->plan;
    }

    /**
     * Vérifier si l'utilisateur peut créer un nouveau lien (quota max de liens).
     */
    public function canCreateLink(User $user, ?Model $team = null): bool
    {
        $subscription = $this->getOrCreateSubscription($user);
        $limitValue = $subscription->getFeatureValue('links_limit');

        if ($limitValue === null || $limitValue === 'unlimited' || (int) $limitValue >= 999999) {
            return true;
        }

        $limit = (int) $limitValue;
        $teamId = $team ? $team->id : ($user->current_team_id ?? $user->personalTeam()?->id);

        $currentCount = Link::query()
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId), fn ($q) => $q->where('user_id', $user->id))
            ->count();

        return $currentCount < $limit;
    }

    /**
     * Obtenir la limite maximale de liens du plan.
     */
    public function getLinksLimit(User $user): int|string
    {
        $subscription = $this->getOrCreateSubscription($user);
        $val = $subscription->getFeatureValue('links_limit');

        if ($val === null || $val === 'unlimited' || (int) $val >= 999999) {
            return 'unlimited';
        }

        return (int) $val;
    }

    /**
     * Vérifier si l'utilisateur a encore du quota de résumés IA pour le mois en cours.
     */
    public function canGenerateAiSummary(User $user): bool
    {
        $subscription = $this->getOrCreateSubscription($user);
        $limitValue = $subscription->getFeatureValue('ai_summaries_monthly');

        if ($limitValue === null || $limitValue === 'unlimited' || (int) $limitValue >= 999999) {
            return true;
        }

        $limit = (int) $limitValue;
        $used = (int) $subscription->getFeatureUsage('ai_summaries_monthly');

        return $used < $limit;
    }

    /**
     * Consommer une unité de résumé IA.
     */
    public function consumeAiSummary(User $user): void
    {
        $subscription = $this->getOrCreateSubscription($user);
        $subscription->recordFeatureUsage('ai_summaries_monthly', 1);
        $subscription->unsetRelation('usage');
    }

    /**
     * Vérifier si l'utilisateur a accès à la sauvegarde Cloud Google Drive.
     */
    public function canUseCloudBackup(User $user): bool
    {
        $subscription = $this->getOrCreateSubscription($user);

        return $subscription->getFeatureValue('cloud_backup') === 'true';
    }

    /**
     * Vérifier si l'équipe peut ajouter un membre supplémentaire.
     */
    public function canAddTeamMember(Model $team, User $owner): bool
    {
        $subscription = $this->getOrCreateSubscription($owner);
        $limitValue = $subscription->getFeatureValue('team_members_limit');

        if ($limitValue === null || $limitValue === 'unlimited' || (int) $limitValue >= 999999) {
            return true;
        }

        $limit = (int) $limitValue;
        $currentMembers = $team->members()->count() + 1; // +1 pour le propriétaire

        return $currentMembers <= $limit;
    }

    /**
     * Obtenir les métriques et jauges de consommation pour l'interface.
     *
     * @return array<string, mixed>
     */
    public function getUsageStats(User $user, ?Model $team = null): array
    {
        $subscription = $this->getOrCreateSubscription($user);
        $plan = $subscription->plan;

        // 1. Liens
        $rawLinksLimit = $subscription->getFeatureValue('links_limit');
        $isLinksUnlimited = ($rawLinksLimit === null || $rawLinksLimit === 'unlimited' || (int) $rawLinksLimit >= 999999);
        $linksLimit = $isLinksUnlimited ? 999999 : (int) $rawLinksLimit;

        $teamId = $team ? $team->id : ($user->current_team_id ?? $user->personalTeam()?->id);
        $linksUsed = Link::query()
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId), fn ($q) => $q->where('user_id', $user->id))
            ->count();

        $linksPercentage = $isLinksUnlimited ? 0 : min(100, (int) round(($linksUsed / max(1, $linksLimit)) * 100));

        // 2. Résumés IA
        $rawAiLimit = $subscription->getFeatureValue('ai_summaries_monthly');
        $isAiUnlimited = ($rawAiLimit === null || $rawAiLimit === 'unlimited' || (int) $rawAiLimit >= 999999);
        $aiLimit = $isAiUnlimited ? 2000 : (int) $rawAiLimit;
        $aiUsed = (int) $subscription->getFeatureUsage('ai_summaries_monthly');
        $aiPercentage = $isAiUnlimited ? 0 : min(100, (int) round(($aiUsed / max(1, $aiLimit)) * 100));

        // 3. Membres d'équipe
        $rawMembersLimit = $subscription->getFeatureValue('team_members_limit');
        $membersLimit = (int) ($rawMembersLimit ?? 1);
        $membersUsed = $team ? ($team->members()->count() + 1) : 1;
        $membersPercentage = min(100, (int) round(($membersUsed / max(1, $membersLimit)) * 100));

        return [
            'plan' => $plan,
            'subscription' => $subscription,
            'is_trial' => $subscription->onTrial(),
            'trial_ends_at' => $subscription->trial_ends_at,
            'ends_at' => $subscription->ends_at,
            'links' => [
                'used' => $linksUsed,
                'limit' => $linksLimit,
                'unlimited' => $isLinksUnlimited,
                'percentage' => $linksPercentage,
                'remaining' => $isLinksUnlimited ? '∞' : max(0, $linksLimit - $linksUsed),
            ],
            'ai_summaries' => [
                'used' => $aiUsed,
                'limit' => $aiLimit,
                'unlimited' => $isAiUnlimited,
                'percentage' => $aiPercentage,
                'remaining' => $isAiUnlimited ? '∞' : max(0, $aiLimit - $aiUsed),
            ],
            'team_members' => [
                'used' => $membersUsed,
                'limit' => $membersLimit,
                'percentage' => $membersPercentage,
                'remaining' => max(0, $membersLimit - $membersUsed),
            ],
            'cloud_backup' => $subscription->getFeatureValue('cloud_backup') === 'true',
            'link_health_automated' => $subscription->getFeatureValue('link_health_automated') === 'true',
        ];
    }

    /**
     * Changer ou souscrire à un plan.
     */
    public function subscribeToPlan(User $user, Plan|string $plan): Subscription
    {
        if (is_string($plan)) {
            $plan = Plan::where('slug', $plan)->firstOrFail();
        }

        $subscription = $this->getOrCreateSubscription($user);
        $subscription->changePlan($plan);

        $user->unsetRelation('planSubscriptions');
        $subscription->unsetRelation('plan');
        $subscription->unsetRelation('usage');

        return $subscription->fresh(['plan.features', 'usage']);
    }
}
