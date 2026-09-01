# 💳 Documentation Technique & Guide d'Utilisation : Abonnements, Plans & Quotas (SaaS)

> **Module :** Monétisation SaaS & Gestion des Quotas  
> **Package :** `nafiswatsiq/subbase` / `laravelcm/laravel-subscriptions`  
> **Service Central :** [`App\Services\SubscriptionQuotaService`](file:///c:/Users/D3vOs/Projets/Laravel/Web/links-vault/app/Services/SubscriptionQuotaService.php)  
> **Page Filament :** [`App\Filament\Pages\ManageSubscription`](file:///c:/Users/D3vOs/Projets/Laravel/Web/links-vault/app/Filament/Pages/ManageSubscription.php)  
> **Tests Pest :** [`tests/Feature/SubscriptionQuotaTest.php`](file:///c:/Users/D3vOs/Projets/Laravel/Web/links-vault/tests/Feature/SubscriptionQuotaTest.php)

---

## 1. Vue d'Ensemble des Plans Tarifaires

L'application intègre 3 paliers d'abonnements conçus pour s'adapter à la fois aux utilisateurs individuels, aux professionnels indépendants et aux équipes collaboratives :

| Fonctionnalité | Plan Gratuit (Free) | Plan Pro | Plan Team |
|---|:---:|:---:|:---:|
| **Prix Mensuel (EUR / XOF)** | **0 €** / 0 FCFA | **4.99 €** / 3 000 FCFA | **14.99 €** / 9 000 FCFA |
| **Prix Annuel (-20% / 2 mois offerts)** | — | **49 €** / 30 000 FCFA | **149 €** / 90 000 FCFA |
| **Limite de Liens** | 100 liens | **Illimité** (∞) | **Illimité** (∞) |
| **Résumés & Tags IA / mois** | 10 | **500** | **2 000** |
| **Sauvegarde Google Drive Cloud** | ❌ (Locale uniquement) | ✅ **1-clic illimité** | ✅ **Multi-comptes** |
| **Membres par Espace (Team)** | 1 (Personnel) | Jusqu'à 3 membres | Jusqu'à 20 membres |
| **Scan de Santé Automatique** | ❌ (Manuel) | ✅ **Automatisé** | ✅ **Automatisé** |
| **Support** | Communautaire | Prioritaire | Dédié |

---

## 2. Architecture Technique & Modèles

Le système repose sur `subbase` couplé au modèle `User` via le trait `HasPlanSubscriptions`.

### Relations & Entités :
- **`Plan`** (`plans`) : Nom, description, prix par devise (`prices` JSON : EUR, XOF, USD), période de facturation, etc.
- **`Feature`** (`features`) : Clé (`slug`), valeur limite (`value`), période de réinitialisation (`resettable_period`, `resettable_interval`).
- **`Subscription`** (`subscriptions`) : Abonnement souscrit par l'utilisateur (identifiant `main`).
- **`SubscriptionUsage`** (`subscription_usage`) : Consommation réelle enregistrée pour les quotas périodiques (ex: `ai_summaries_monthly`).

---

## 3. Service Centralisé : `SubscriptionQuotaService`

Toutes les vérifications et consommations de quotas sont centralisées dans `App\Services\SubscriptionQuotaService` :

```php
// 1. Obtenir l'abonnement actif (assigne automatiquement le plan Free si aucun abonnement)
$subscription = $quotaService->getOrCreateSubscription($user);

// 2. Vérifier si l'utilisateur peut créer un lien
if (! $quotaService->canCreateLink($user, $team)) {
    throw ValidationException::withMessages(['url' => 'Limite de liens atteinte.']);
}

// 3. Vérifier et consommer un résumé IA mensuel
if ($quotaService->canGenerateAiSummary($user)) {
    $quotaService->consumeAiSummary($user);
}

// 4. Vérifier l'accès à la sauvegarde Google Drive
$hasCloud = $quotaService->canUseCloudBackup($user);

// 5. Calcul des métriques & jauges de consommation pour l'interface
$stats = $quotaService->getUsageStats($user, $team);
// Retourne : [ 'links' => [...], 'ai_summaries' => [...], 'team_members' => [...], 'cloud_backup' => bool ]
```

---

## 4. Application des Quotas dans le Workflow

1. **Création de Lien ([`CreateLinkAction.php`](file:///c:/Users/D3vOs/Projets/Laravel/Web/links-vault/app/Actions/LinkActions/CreateLinkAction.php))** :
   - Vérification `SubscriptionQuotaService::canCreateLink($user, $team)`.
   - Lève une `ValidationException` personnalisée avec invitation à passer au plan Pro si la limite est atteinte.
2. **API Chrome Extension ([`LinkController.php`](file:///c:/Users/D3vOs/Projets/Laravel/Web/links-vault/app/Http/Controllers/Api/LinkController.php))** :
   - Retourne un statut HTTP `403 Forbidden` avec code d'erreur `QUOTA_EXCEEDED` et message explicite pour la popup de l'extension.
3. **Résumés IA ([`GenerateAiSummaryAction.php`](file:///c:/Users/D3vOs/Projets/Laravel/Web/links-vault/app/Actions/LinkActions/GenerateAiSummaryAction.php))** :
   - Vérifie `canGenerateAiSummary($user)` avant d'invoquer le modèle IA.
   - Enregistre la consommation `consumeAiSummary($user)` une fois le résumé généré.

---

## 5. Interface Utilisateur Filament (`ManageSubscription`)

Accessible depuis le menu latéral de l'application sous **Paramètres > Abonnement & Quotas** (`/app/{tenant}/manage-subscription`) :

- **Bandeau récapitulatif** de l'offre actuelle avec statut d'activité.
- **3 Jauges en temps réel** :
  1. *Liens sauvegardés* (ex: `12 / 100` ou `∞`).
  2. *Résumés IA du mois* avec date de réinitialisation automatique.
  3. *Membres d'équipe* et statut de synchronisation Google Drive.
- **Sélecteur de Fréquence** : Mensuel vs Annuel avec badge de réduction `-20%`.
- **Sélecteur Multi-Devises** : `€ EUR`, `FCFA (Afrique)`, `$ USD`.
- **3 Cartes de Tarifs** modernes et responsives avec bouton d'activation immédiate et badge *« LE PLUS POPULAIRE »* sur l'offre Pro.
- **Section Réassurance** : Moyens de paiement acceptés (Cartes Bancaires, Mobile Money Wave / Orange / MTN / Moov).
