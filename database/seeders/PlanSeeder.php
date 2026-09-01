<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Nafiswatsiq\Subbase\Models\Feature;
use Nafiswatsiq\Subbase\Models\Plan;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Plan Gratuit (Free)
        $freePlan = Plan::updateOrCreate(
            ['slug' => 'free'],
            [
                'name' => [
                    'fr' => 'Gratuit',
                    'en' => 'Free',
                ],
                'description' => [
                    'fr' => 'Idéal pour découvrir LinksVault et sauvegarder vos premiers favoris.',
                    'en' => 'Perfect for discovering LinksVault and storing your first bookmarks.',
                ],
                'is_active' => true,
                'featured' => false,
                'price' => 0.00,
                'signup_fee' => 0.00,
                'currency' => 'EUR',
                'prices' => [
                    'EUR' => 0.00,
                    'XOF' => 0,
                    'USD' => 0.00,
                ],
                'trial_period' => 0,
                'trial_interval' => 'day',
                'invoice_period' => 1,
                'invoice_interval' => 'month',
                'sort_order' => 1,
            ]
        );

        $this->syncFeatures($freePlan, [
            [
                'name' => ['fr' => 'Limite de liens', 'en' => 'Links Limit'],
                'slug' => 'links_limit',
                'value' => '100',
                'resettable_period' => 0,
                'sort_order' => 1,
            ],
            [
                'name' => ['fr' => 'Résumés IA / mois', 'en' => 'AI Summaries / month'],
                'slug' => 'ai_summaries_monthly',
                'value' => '10',
                'resettable_period' => 1,
                'resettable_interval' => 'month',
                'sort_order' => 2,
            ],
            [
                'name' => ['fr' => 'Sauvegarde Google Drive Cloud', 'en' => 'Google Drive Cloud Backup'],
                'slug' => 'cloud_backup',
                'value' => 'false',
                'resettable_period' => 0,
                'sort_order' => 3,
            ],
            [
                'name' => ['fr' => 'Membres d\'équipe', 'en' => 'Team Members'],
                'slug' => 'team_members_limit',
                'value' => '1',
                'resettable_period' => 0,
                'sort_order' => 4,
            ],
            [
                'name' => ['fr' => 'Scan de santé automatique', 'en' => 'Automated Link Health Scan'],
                'slug' => 'link_health_automated',
                'value' => 'false',
                'resettable_period' => 0,
                'sort_order' => 5,
            ],
        ]);

        // 2. Plan Pro
        $proPlan = Plan::updateOrCreate(
            ['slug' => 'pro'],
            [
                'name' => [
                    'fr' => 'Pro',
                    'en' => 'Pro',
                ],
                'description' => [
                    'fr' => 'Pour les créateurs et professionnels désirant une mémoire numérique illimitée enrichie par l\'IA.',
                    'en' => 'For creators and professionals wanting unlimited, AI-enriched bookmarking.',
                ],
                'is_active' => true,
                'featured' => true,
                'price' => 4.99,
                'signup_fee' => 0.00,
                'currency' => 'EUR',
                'prices' => [
                    'EUR' => 4.99,
                    'XOF' => 3000,
                    'USD' => 5.49,
                ],
                'trial_period' => 7,
                'trial_interval' => 'day',
                'invoice_period' => 1,
                'invoice_interval' => 'month',
                'sort_order' => 2,
            ]
        );

        $this->syncFeatures($proPlan, [
            [
                'name' => ['fr' => 'Limite de liens', 'en' => 'Links Limit'],
                'slug' => 'links_limit',
                'value' => '999999',
                'resettable_period' => 0,
                'sort_order' => 1,
            ],
            [
                'name' => ['fr' => 'Résumés IA / mois', 'en' => 'AI Summaries / month'],
                'slug' => 'ai_summaries_monthly',
                'value' => '500',
                'resettable_period' => 1,
                'resettable_interval' => 'month',
                'sort_order' => 2,
            ],
            [
                'name' => ['fr' => 'Sauvegarde Google Drive Cloud', 'en' => 'Google Drive Cloud Backup'],
                'slug' => 'cloud_backup',
                'value' => 'true',
                'resettable_period' => 0,
                'sort_order' => 3,
            ],
            [
                'name' => ['fr' => 'Membres d\'équipe', 'en' => 'Team Members'],
                'slug' => 'team_members_limit',
                'value' => '3',
                'resettable_period' => 0,
                'sort_order' => 4,
            ],
            [
                'name' => ['fr' => 'Scan de santé automatique', 'en' => 'Automated Link Health Scan'],
                'slug' => 'link_health_automated',
                'value' => 'true',
                'resettable_period' => 0,
                'sort_order' => 5,
            ],
        ]);

        // 3. Plan Team (Entreprise & Collaboration)
        $teamPlan = Plan::updateOrCreate(
            ['slug' => 'team'],
            [
                'name' => [
                    'fr' => 'Équipe',
                    'en' => 'Team',
                ],
                'description' => [
                    'fr' => 'Collaboration fluide, rôles d\'équipe avancés, multi-drives et quotas massifs.',
                    'en' => 'Seamless collaboration, advanced team roles, multi-drives, and massive quotas.',
                ],
                'is_active' => true,
                'featured' => false,
                'price' => 14.99,
                'signup_fee' => 0.00,
                'currency' => 'EUR',
                'prices' => [
                    'EUR' => 14.99,
                    'XOF' => 9000,
                    'USD' => 16.49,
                ],
                'trial_period' => 14,
                'trial_interval' => 'day',
                'invoice_period' => 1,
                'invoice_interval' => 'month',
                'sort_order' => 3,
            ]
        );

        $this->syncFeatures($teamPlan, [
            [
                'name' => ['fr' => 'Limite de liens', 'en' => 'Links Limit'],
                'slug' => 'links_limit',
                'value' => '999999',
                'resettable_period' => 0,
                'sort_order' => 1,
            ],
            [
                'name' => ['fr' => 'Résumés IA / mois', 'en' => 'AI Summaries / month'],
                'slug' => 'ai_summaries_monthly',
                'value' => '2000',
                'resettable_period' => 1,
                'resettable_interval' => 'month',
                'sort_order' => 2,
            ],
            [
                'name' => ['fr' => 'Sauvegarde Google Drive Cloud', 'en' => 'Google Drive Cloud Backup'],
                'slug' => 'cloud_backup',
                'value' => 'true',
                'resettable_period' => 0,
                'sort_order' => 3,
            ],
            [
                'name' => ['fr' => 'Membres d\'équipe', 'en' => 'Team Members'],
                'slug' => 'team_members_limit',
                'value' => '20',
                'resettable_period' => 0,
                'sort_order' => 4,
            ],
            [
                'name' => ['fr' => 'Scan de santé automatique', 'en' => 'Automated Link Health Scan'],
                'slug' => 'link_health_automated',
                'value' => 'true',
                'resettable_period' => 0,
                'sort_order' => 5,
            ],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $features
     */
    protected function syncFeatures(Plan $plan, array $features): void
    {
        foreach ($features as $featData) {
            Feature::updateOrCreate(
                [
                    'plan_id' => $plan->id,
                    'slug' => $featData['slug'],
                ],
                [
                    'name' => $featData['name'],
                    'value' => $featData['value'],
                    'resettable_period' => $featData['resettable_period'] ?? 0,
                    'resettable_interval' => $featData['resettable_interval'] ?? 'month',
                    'sort_order' => $featData['sort_order'] ?? 0,
                ]
            );
        }
    }
}
