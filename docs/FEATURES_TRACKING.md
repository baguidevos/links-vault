# 📚 LinksVault — Registre Global & Suivi des Fonctionnalités

> **Document de référence pour la rédaction des futures documentations techniques et utilisateurs.**  
> *Dernière mise à jour : 31 Août 2026*  
> *Statut du projet : Phase 1 (100%), Phase 2 (100%), Phase 3 (95%), Phase 4 & 5 (Planifiées)*

---

## 🧭 Sommaire

1. [Architecture & Déclinaisons du Projet](#1-architecture--d%C3%A9clinaisons-du-projet)
2. [Recensement Exhaustif des Fonctionnalités Développées](#2-recensement-exhaustif-des-fonctionnalit%C3%A9s-d%C3%A9velopp%C3%A9es)
   - [2.1 Multi-Tenancy, Authentification & Espaces Collaboratifs](#21-multi-tenancy-authentification--espaces-collaboratifs)
   - [2.2 Gestion & Modélisation des Liens & Connaissances](#22-gestion--mod%C3%A9lisation-des-liens--connaissances)
   - [2.3 Interface Utilisateur, Vues & Expérience (UI/UX)](#23-interface-utilisateur-vues--exp%C3%A9rience-uiux)
   - [2.4 Application Desktop Native (NativePHP / Electron)](#24-application-desktop-native-nativephp--electron)
   - [2.5 Extension Navigateur Web Clipper (Manifest V3)](#25-extension-navigateur-web-clipper-manifest-v3)
   - [2.6 API REST & Sécurité (Laravel Sanctum)](#26-api-rest--s%C3%A9curit%C3%A9-laravel-sanctum)
   - [2.7 Qualité & Suite de Tests Automatisés](#27-qualit%C3%A9--suite-de-tests-automatis%C3%A9s)
3. [Feuille de Route des Fonctionnalités Futures](#3-feuille-de-route-des-fonctionnalit%C3%A9s-futures)
   - [3.1 Phase 4 : Intelligence Artificielle (Laravel AI SDK)](#31-phase-4--intelligence-artificielle-laravel-ai-sdk)
   - [3.2 Phase 5 : Import/Export, Collaboration & Monétisation SaaS](#32-phase-5--importexport-collaboration--mon%C3%A9tisation-saas)
4. [Guide de Rédaction pour les Futures Documentations](#4-guide-de-r%C3%A9daction-pour-les-futures-documentations)

---

## 1. Architecture & Déclinaisons du Projet

LinksVault est conçu comme une plateforme unifiée déclinée en 3 piliers :

```
                        ┌────────────────────────────────────────┐
                        │          LINKSVAULT ECOSYSTEM          │
                        └───────────────────┬────────────────────┘
                                            │
         ┌──────────────────────────────────┼──────────────────────────────────┐
         │                                  │                                  │
         ▼                                  ▼                                  ▼
┌──────────────────┐               ┌──────────────────┐               ┌──────────────────┐
│  Web Application │               │  Desktop Native  │               │ Browser Clipper  │
│  (Laravel 13 SaaS│               │ (NativePHP /     │               │ (Chrome/Edge/    │
│  + Filament v5)  │               │  Electron App)   │               │ Brave Ext. V3)   │
└──────────────────┘               └──────────────────┘               └──────────────────┘
         │                                  │                                  │
         └──────────────────────────────────┴──────────────────────────────────┘
                                            │
                                            ▼
                        ┌────────────────────────────────────────┐
                        │           CORE BACKEND & API           │
                        │    (Multi-Tenant, Sanctum, SQLite/     │
                        │     Postgres, Laravel AI SDK ready)    │
                        └────────────────────────────────────────┘
```

### 🛠️ Stack Technologique Fondamentale :
- **Backend** : PHP 8.4+, Laravel 13
- **Administration & Back-office** : Filament v5 (Panels, Tables, Forms, Infolists, Notifications)
- **Réactivité Frontend** : Livewire v4, Alpine.js, Tailwind CSS v4
- **Multi-Tenancy** : FilaTeams (équipes personnelles & collaboratives)
- **Moteur Desktop** : NativePHP Desktop (Electron Windows/macOS)
- **Extension Navigateur** : WebExtension Manifest V3 (Vanilla JS, CSS moderne)
- **Sécurité API** : Laravel Sanctum (Tokens personnels révocables)
- **Tests** : Pest PHP 4 / PHPUnit 12

---

## 2. Recensement Exhaustif des Fonctionnalités Développées

### 2.1 Multi-Tenancy, Authentification & Espaces Collaboratifs

| Fonctionnalité | Description Technique & Fonctionnelle | Statut |
| :--- | :--- | :---: |
| **Création Automatique d'Espace Personnel** | À l'inscription (`Registered` event), un espace personnel (`Personal Team`) est automatiquement créé pour l'utilisateur via `CreatePersonalTeam` listener. | 🟢 Terminé |
| **Multi-Tenancy avec FilaTeams** | Séparation étanche des données par espace (`team_id`). Chaque ressource (Liens, Dossiers, Catégories, Tags) appartient strictement au tenant actif. | 🟢 Terminé |
| **Gestion des Rôles & Permissions** | Support des rôles d'équipe : *Propriétaire (Owner)*, *Administrateur (Admin)*, *Membre (Member)* avec politiques d'accès (`TeamPolicy`). | 🟢 Terminé |
| **Bascule Rapide d'Espace (Tenant Switcher)** | Sélecteur de tenant intégré dans la barre latérale Filament permettant de passer d'un espace personnel à un espace d'équipe sans se reconnecter. | 🟢 Terminé |
| **Pages d'Inscription & Connexion Personnalisées** | Formulaires Filament v5 personnalisés avec branding LinksVault, logos vectoriels et gestion des mots de passe sécurisés. | 🟢 Terminé |

---

### 2.2 Gestion & Modélisation des Liens & Connaissances

| Fonctionnalité | Description Technique & Fonctionnelle | Statut |
| :--- | :--- | :---: |
| **Métadonnées Exhaustives** | Enregistrement de : `url`, `title`, `description`, `objective` (objectif personnel/pro), `reading_time`, `author`, `published_at`, `favicon_url`, `thumbnail_url`. | 🟢 Terminé |
| **Détection Automatique des Types de Contenu** | Enum `ContentType` avec détection automatique : `Youtube`, `GoogleDrive`, `GoogleDoc`, `GoogleSheet`, `GoogleSlides`, `GoogleForm`, `Article`, `Pdf`, `Image`, `Other`. | 🟢 Terminé |
| **Détecteur Spécialisé YouTube** | Extraction automatique de l'ID vidéo, résolution de la vignette haute résolution (`hqdefault.jpg`), affichage d'un bouton Play overlay interactif. | 🟢 Terminé |
| **Organisation par Dossiers (`Folder`)** | Classement des liens dans des dossiers avec icônes personnalisées, description et liaison optionnelle avec une catégorie. | 🟢 Terminé |
| **Catégorisation (`Category`)** | Classement thématique transversal (ex: *Développement, Design, Marketing, IA*). | 🟢 Terminé |
| **Système de Tags / Étiquettes** | Association multiple de tags à chaque lien avec chips colorées et filtrage rapide. | 🟢 Terminé |
| **Statut Favori ⭐ Interactif** | Bouton permanent à double état (doré ⭐ / contour ☆) permettant l'ajout/retrait instantané en 1 clic sans rechargement de page. | 🟢 Terminé |
| **Visibilité Granulaire des Liens** | Enum `LinkVisibility` : `Public`, `Team` (partagé avec l'équipe), `Private` (visible uniquement par le créateur), `Restricted` (accessible uniquement à une liste restreinte de membres). | 🟢 Terminé |
| **Compteur de Consultations (`visit_count`)** | Incrémentation automatique du nombre d'ouvertures d'un lien pour mesurer l'intérêt et la popularité. | 🟢 Terminé |
| **Archivage Réversible** | Possibilité d'archiver les liens traités pour épurer l'espace de travail tout en conservant l'historique consultable. | 🟢 Terminé |

---

### 2.3 Interface Utilisateur, Vues & Expérience (UI/UX)

| Fonctionnalité | Description Technique & Fonctionnelle | Statut |
| :--- | :--- | :---: |
| **Vue Grille Visuelle (Cards Grid)** | Affichage en cartes modernes responsives (1 à 4 colonnes) avec aperçu média 16:9, badges frosted-glass, favicon, dossier, catégorie et tags. | 🟢 Terminé |
| **Vue Table Tabulaire Classique** | Tableau complet pour gestion dense : tri sur colonnes, sélection multiple et actions en masse. | 🟢 Terminé |
| **Bascule Dynamique Grille / Table** | Bouton de bascule en en-tête avec persistance du mode préféré en session utilisateur (`links_view_mode`). | 🟢 Terminé |
| **Moteur de Recherche Global de Table** | Recherche plein texte instantanée sur `title`, `url`, `description`, `objective`, `folder.name`, `category.name` avec délai de frappe optimisé (debounce `300ms`). | 🟢 Terminé |
| **Filtres Avancés Above Content** | Barre de filtrage multi-critères positionnée au-dessus des cartes (par dossier, catégorie, type de média, favoris, visibilité, archives). | 🟢 Terminé |
| **Palette de Commandes Globale (`Ctrl + K`)** | Modal de recherche rapide universelle pour trouver n'importe quel lien, dossier ou commande au clavier. | 🟢 Terminé |
| **Navigation SPA Instantanée (`wire:navigate`)** | Navigation ultra-rapide sans rechargement complet de la page sur l'ensemble des boutons et liens de cartes. | 🟢 Terminé |
| **Responsive Mobile-First & Vue Détails Épurée** | Adaptation aux petits écrans : troncature élégante des longs titres, breadcrumbs condensés, regroupement des actions secondaires dans un menu dropdown « Plus » (`ActionGroup`). | 🟢 Terminé |
| **Directives Blade Multi-Plateformes** | Directives sur-mesure enregistrées dans `AppServiceProvider` : `@desktop`, `@web`, `@mobile`, `@windows`, `@mac` pour conditionner les vues selon l'environnement. | 🟢 Terminé |

---

### 2.4 Application Desktop Native (NativePHP / Electron)

| Fonctionnalité | Description Technique & Fonctionnelle | Statut |
| :--- | :--- | :---: |
| **Fenêtre Electron Native** | Fenêtre desktop autonome avec configuration des dimensions minimales, titre et icônes d'application. | 🟢 Terminé |
| **Persistance des États & Base Interne** | Sauvegarde locale des états, gestion des sessions et support de base de données SQLite locale (`nativephp.sqlite`). | 🟢 Terminé |
| **MenuBar / System Tray** | Icône dans la barre des tâches / zone de notification avec menu contextuel d'accès rapide. | 🟢 Terminé |
| **Boutons d'Historique Dédiés au Desktop** | Flèches de navigation (← Précédent, → Suivant, ⟳ Actualiser) affichées uniquement sur l'application Desktop grâce à la directive `@desktop`. | 🟢 Terminé |

---

### 2.5 Extension Navigateur Web Clipper (Manifest V3)

| Fonctionnalité | Description Technique & Fonctionnelle | Statut |
| :--- | :--- | :---: |
| **Capture en 1 Clic (`Alt + S`)** | Déclenchement instantané du popup de capture via raccourci clavier global ou icône de barre d'outils. | 🟢 Terminé |
| **Extraction Intelligente du DOM** | Extraction automatique de l'URL canonique, Titre nettoyé, Meta Description, Image OpenGraph / Twitter, Favicon haute qualité. | 🟢 Terminé |
| **Gestion Avancée YouTube dans le Clipper** | Détection en temps réel des vidéos YouTube (y compris navigation SPA YouTube) pour capturer le titre exact et la vignette HD. | 🟢 Terminé |
| **Capture de Citations / Sélections** | Si du texte est sélectionné sur la page, un bouton « + Insérer la sélection » permet de l'ajouter instantanément en note. | 🟢 Terminé |
| **Double Mode d'Authentification** | Connexion automatique par Email + Mot de passe (générant le token Sanctum) ou saisie manuelle de clé API. | 🟢 Terminé |
| **Préréglages Serveur Intégrés** | Boutons 1-clic pour cibler `🌐 Web (https://links-vault.test)`, `🖥️ Desktop (http://127.0.0.1:8100)` ou `http://localhost:8000`. | 🟢 Terminé |
| **Organisation Complète à la Volée** | Choix du Workspace/Team, Dossier de destination, Catégorie, saisie de Tags avec auto-complétion, case Favori ⭐ et Résumé IA. | 🟢 Terminé |
| **Menu Contextuel Clic Droit** | Options de clic droit : *« Enregistrer cette page »*, *« Enregistrer ce lien »*, *« Enregistrer la sélection en note »*. | 🟢 Terminé |
| **Prévention des Doublons (HTTP 409)** | Détection des liens déjà existants avec message explicite et bouton direct pour ouvrir la fiche dans le Vault. | 🟢 Terminé |

---

### 2.6 API REST & Sécurité (Laravel Sanctum)

| Endpoint | Méthode | Rôle & Fonction |
| :--- | :---: | :--- |
| `/api/auth/token` | `POST` | Authentifie l'utilisateur et émet un Personal Access Token Sanctum avec nom de périphérique. |
| `/api/auth/revoke` | `POST` | Révoque le jeton d'accès actuel (déconnexion sécurisée). |
| `/api/me` | `GET` | Renvoie les informations de l'utilisateur connecté et valide le jeton. |
| `/api/context` | `GET` | Renvoie l'ensemble des Teams, Dossiers, Catégories et Tags accessibles pour l'utilisateur. |
| `/api/links/preview` | `POST` | Enrichit une URL côté serveur (OpenGraph, scraping métadonnées, miniatures). |
| `/api/links` | `POST` | Crée un nouveau lien dans l'espace spécifié avec gestion des doublons et déclenchement des actions associées. |

---

### 2.7 Qualité & Suite de Tests Automatisés

- **Outil de test** : Pest PHP 4 / PHPUnit 12
- **Couverture actuelle** : **32 tests automatisés passants (119 assertions)**
- **Domaines testés** :
  - Inscription d'utilisateur & création automatique d'espace personnel.
  - Connexion & authentification Filament / Sanctum.
  - Isolation stricte des données entre locataires (Multi-Tenancy security).
  - Gestion du CRUD des liens et validation des permissions.

---

## 3. Feuille de Route des Fonctionnalités Futures

### 3.1 Phase 4 : Intelligence Artificielle (Laravel AI SDK)

| Fonctionnalité Prévue | Objectif Technique | Impact Utilisateur |
| :--- | :--- | :--- |
| **Résumés Automatiques Intelligents** | Utilisation de `Laravel\Ai` pour générer un résumé synthétique en 3 points clés à chaque enregistrement d'article ou de vidéo. | Gain de temps majeur lors de la relecture de la veille. |
| **Auto-Tagging & Classification IA** | Analyse du contenu de la page pour proposer automatiquement 3 à 5 tags pertinents et suggérer le dossier le plus approprié. | Zéro friction d'organisation pour l'utilisateur. |
| **Transcription & Synthèse YouTube** | Extraction de la transcription des vidéos YouTube et génération d'un compte-rendu textuel structuré. | Retrouver des passages clés d'une vidéo sans devoir la regarder en entier. |
| **Recherche Sémantique & Chat RAG** | Vectorisation des contenus sauvegardés pour permettre à l'utilisateur de « chatter » avec sa base de liens (*ex: « Quels sont les articles qui parlent de Tailwind v4 ? »*). | Transformation de LinksVault en second cerveau numérique interactif. |

---

### 3.2 Phase 5 : Import/Export, Collaboration & Monétisation SaaS

| Fonctionnalité Prévue | Objectif Technique | Impact Utilisateur |
| :--- | :--- | :--- |
| **Importateur Universel de Signets** | Parser HTML / JSON pour importer des signets depuis Chrome, Firefox, Safari, Pocket, Raindrop.io et fichiers CSV. | Onboarding instantané des nouveaux utilisateurs sans perte d'historique. |
| **Exportateur Complet Standardisé** | Générateur d'export aux formats Netscape HTML Bookmark et JSON structuré. | Réassurance de souveraineté des données (zéro enfermement propriétaire). |
| **Gestion des Abonnements (Subbase / Stripe)** | Gestion des plans tarifaires (*Free, Pro, Team*) avec quotas sur le nombre de liens et l'usage des fonctionnalités IA. | Modèle de monétisation SaaS clé en main. |
| **Sauvegarde & Synchronisation Cloud** | Intégration de connecteurs Cloud (Google Drive, Dropbox, AWS S3) pour sauvegarde automatique des métadonnées et captures d'écran. | Sécurité maximale et redondance des sauvegardes. |

---

## 4. Guide de Rédaction pour les Futures Documentations

Lors de la rédaction des documentations finales, ce document servira de plan directeur selon la répartition suivante :

### 📘 Pour la Documentation Technique (Développeurs & DevOps) :
1. **Architecture logicielle** : Modèle de données Eloquent, relations polymorphiques des tags, scopes multi-tenants.
2. **API Reference** : Spécifications OpenAPI/Swagger de tous les endpoints `/api/*`.
3. **Packaging Desktop** : Procédures de build NativePHP pour Windows (`.exe` / `.msi`) et macOS (`.dmg`).
4. **Extension Clipper** : Structure Manifest V3, cycle de vie du background service worker et permissions requises.
5. **Intégration IA** : Configuration des providers `Laravel\Ai` (OpenAI, Gemini, Anthropic, Ollama).

### 📗 Pour la Documentation Utilisateur (Guides & Tutoriels) :
1. **Démarrage Rapide** : Création de compte, découverte de l'espace personnel, installation de l'extension de navigateur.
2. **Guide de la Capture** : Utilisation du raccourci `Alt + S`, capture de vidéos YouTube, enregistrement de notes depuis une sélection.
3. **Organisation Efficace** : Utilisation des dossiers, catégories, tags et bascule entre la vue Grille et la vue Table.
4. **Productivité & Raccourcis** : Navigation clavier avec `Ctrl + K`, favoris rapides ⭐, navigation SPA instantanée.
5. **Collaboration en Équipe** : Invitation de collaborateurs, gestion des rôles et partages de dossiers.

---
*Document maintenu automatiquement pour l'équipe LinksVault.*
