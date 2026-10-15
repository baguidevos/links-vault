<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\SyncSetting;
use App\Models\Team;
use App\Services\SasPayService;
use App\Services\SubscriptionQuotaService;
use App\Services\Sync\DesktopSyncService;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Nafiswatsiq\Subbase\Models\Plan;
use UnitEnum;

class ManageSubscription extends Page
{
    protected static string|BackedEnum|null $navigationIcon = TablerIcon::CreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 40;

    protected string $view = 'filament.pages.manage-subscription';

    public string $billingPeriod = 'monthly';

    public string $currency = 'XOF';

    public bool $is_syncing_subscription = false;

    public bool $is_desktop = false;

    public ?string $web_server_url = null;

    public function mount(): void
    {
        $this->is_desktop = DesktopSyncSettings::isDesktop();
        $user = auth()->user();
        if ($user) {
            $settings = SyncSetting::where('user_id', $user->id)->first();
            $this->web_server_url = $settings?->server_url;
        }

        if ($user && $user->locale) {
            $currencyMap = [
                'fr-CI' => 'XOF',
                'fr-SN' => 'XOF',
                'fr-BJ' => 'XOF',
                'fr-TG' => 'XOF',
                'fr-CM' => 'XAF',
                'en-US' => 'USD',
                'fr' => 'XOF',
            ];
            $this->currency = $currencyMap[$user->locale] ?? 'XOF';
        }
    }

    public static function getNavigationLabel(): string
    {
        return __('Abonnement & Quotas');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Mon Abonnement & Quotas');
    }

    public function getHeading(): string|Htmlable
    {
        return __('Gérer mon Abonnement');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Visualisez votre consommation, vos limites et faites évoluer votre offre en toute simplicité.');
    }

    public function setBillingPeriod(string $period): void
    {
        $this->billingPeriod = in_array($period, ['monthly', 'yearly'], true) ? $period : 'monthly';
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = in_array($currency, ['EUR', 'XOF', 'USD'], true) ? $currency : 'EUR';
    }

    public function syncSubscriptionWithCloud(DesktopSyncService $syncService): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $this->is_syncing_subscription = true;
        $tenant = Filament::getTenant();
        $team = $tenant instanceof Team ? $tenant : ($tenant ? Team::find($tenant->id) : $user->teams()->first());

        $result = $syncService->sync($user, $team);
        $this->is_syncing_subscription = false;

        if ($result['success'] ?? false) {
            $quotaService = app(SubscriptionQuotaService::class);
            $plan = $quotaService->getCurrentPlan($user);
            $planName = $plan->name['fr'] ?? $plan->name['en'] ?? ucfirst($plan->slug);

            Notification::make()
                ->title(__('Abonnement synchronisé avec succès !'))
                ->body(__('Votre formule active sur ce poste est désormais : :plan.', ['plan' => $planName]))
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title(__('Erreur de synchronisation'))
                ->body($result['error'] ?? __('Impossible de contacter le serveur Web pour actualiser l\'abonnement.'))
                ->danger()
                ->send();
        }
    }

    public function switchPlan(string $planSlug, SubscriptionQuotaService $quotaService, SasPayService $sasPayService): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $plan = Plan::where('slug', $planSlug)->first();
        if (! $plan) {
            Notification::make()
                ->title(__('Plan introuvable'))
                ->danger()
                ->send();

            return;
        }

        // Si passage vers le plan gratuit : bascule directe
        if ($plan->slug === 'free') {
            $quotaService->subscribeToPlan($user, $plan);

            Notification::make()
                ->title(__('Passage au plan Gratuit effectué'))
                ->body(__('Votre abonnement est désormais repassé sur l\'offre Gratuite.'))
                ->info()
                ->send();

            return;
        }

        // Sur Desktop, si les clés SasPay ne sont pas configurées en local mais qu'un serveur Web est lié
        if ($this->is_desktop && empty(config('services.saspay.api_key')) && $this->web_server_url) {
            $tenant = Filament::getTenant();
            $tenantSlug = $tenant?->slug ?? 'personal';
            $webManageUrl = rtrim($this->web_server_url, '/')."/app/{$tenantSlug}/manage-subscription";

            Notification::make()
                ->title(__('Paiement centralisé sur le Web'))
                ->body(__('Redirection vers votre interface Web LinksVault pour finaliser le paiement sécurisé.'))
                ->info()
                ->send();

            $this->redirect($webManageUrl);

            return;
        }

        // Pour les plans payants (Pro, Team) : initiation du Checkout SasPay
        try {
            $result = $sasPayService->createCheckoutSession(
                user: $user,
                plan: $plan,
                period: $this->billingPeriod,
                currency: $this->currency
            );

            if ($result['success'] && ! empty($result['checkout_url'])) {
                $this->redirect($result['checkout_url']);

                return;
            }

            Notification::make()
                ->title(__('Impossible d\'initialiser le paiement'))
                ->body($result['error'] ?? __('Une erreur est survenue lors de la communication avec SasPay.'))
                ->danger()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('Erreur de configuration paiement'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth()->user();
        $team = Filament::getTenant() ?? $user?->personalTeam();
        $quotaService = app(SubscriptionQuotaService::class);

        $stats = $user ? $quotaService->getUsageStats($user, $team) : [];
        $plans = Plan::with('features')->where('is_active', true)->orderBy('sort_order')->get();

        return [
            'stats' => $stats,
            'plans' => $plans,
            'currentPlan' => $stats['plan'] ?? null,
            'currentSubscription' => $stats['subscription'] ?? null,
            'currency' => $this->currency,
            'billingPeriod' => $this->billingPeriod,
        ];
    }
}
