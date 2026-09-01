<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\SubscriptionQuotaService;
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

    public string $currency = 'EUR';

    public function mount(): void
    {
        $user = auth()->user();
        if ($user && $user->locale) {
            $currencyMap = [
                'fr-CI' => 'XOF',
                'fr-SN' => 'XOF',
                'fr-BJ' => 'XOF',
                'fr-TG' => 'XOF',
                'fr-CM' => 'XAF',
                'en-US' => 'USD',
                'fr' => 'EUR',
            ];
            $this->currency = $currencyMap[$user->locale] ?? 'EUR';
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

    public function switchPlan(string $planSlug, SubscriptionQuotaService $quotaService): void
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

        $quotaService->subscribeToPlan($user, $plan);

        Notification::make()
            ->title(__('Abonnement mis à jour avec succès ! 🎉'))
            ->body(__('Vous êtes désormais sur le plan :plan.', ['plan' => $plan->name[app()->getLocale()] ?? $plan->name['fr'] ?? $plan->slug]))
            ->success()
            ->send();
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
