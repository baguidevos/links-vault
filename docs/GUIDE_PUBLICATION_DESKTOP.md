# Guide de Publication et Mise à Jour Desktop (NativePHP v2)

Ce guide décrit la procédure complète pour compiler, publier et déployer les mises à jour automatiques de l'application **LinksVault Desktop**.

---

## 1. Vue d'ensemble & Architecture

### Comment fonctionne l'application Desktop ?
- **Runtime** : LinksVault Desktop est propulsé par **NativePHP Desktop v2**, associant un runtime Electron, un serveur web PHP interne, et une base de données locale **SQLite** (`{appdata}/database/database.sqlite`).
- **Auto-Updater** : L'application intègre le composant `AutoUpdater` de NativePHP. En environnement de production empaqueté, l'application vérifie périodiquement la présence d'une nouvelle version sur **GitHub Releases**.
- **Application des mises à jour** : Lorsqu'une nouvelle version est détectée, elle est téléchargée silencieusement en arrière-plan. Au redémarrage (ou via le bouton *« Redémarrer & Installer »*), les fichiers de l'application sont mis à jour et les nouvelles migrations SQLite sont automatiquement appliquées.

---

## 2. Configuration & Pré-requis

### Variables d'environnement (`.env`)

Vérifiez que votre fichier `.env` contient les variables suivantes :

```dotenv
# NativePHP Desktop & Mises à jour
NATIVEPHP_APP_VERSION=1.0.0
NATIVEPHP_UPDATER_ENABLED=true
NATIVEPHP_UPDATER_PROVIDER=github
GITHUB_OWNER=baguidevos
GITHUB_REPO=links-vault
GITHUB_TOKEN=ghp_xxxxxxxxxxxxxxxxxxxxxx
GITHUB_AUTOUPDATE_TOKEN=
GITHUB_RELEASE_TYPE=draft
```

| Variable | Description |
| :--- | :--- |
| `NATIVEPHP_APP_VERSION` | Version actuelle de l'application (ex: `1.0.0`, `1.0.1`). **Doit être incrémentée à chaque release.** |
| `NATIVEPHP_UPDATER_ENABLED` | Active ou désactive le mécanisme de mise à jour automatique (`true`/`false`). |
| `NATIVEPHP_UPDATER_PROVIDER` | Provider d'hébergement des releases (`github`, `s3`, `spaces`). |
| `GITHUB_OWNER` | Propriétaire du dépôt GitHub (`baguidevos`). |
| `GITHUB_REPO` | Nom du dépôt GitHub (`links-vault`). |
| `GITHUB_TOKEN` | Jeton d'accès GitHub personnel (PAT) avec le scope `repo` (requis uniquement pour la publication locale). |
| `GITHUB_AUTOUPDATE_TOKEN` | *(Optionnel)* Requis **uniquement si le dépôt GitHub est privé** afin que les utilisateurs puissent télécharger la mise à jour sans authentification utilisateur. |
| `GITHUB_RELEASE_TYPE` | Type de release créée lors du publish : `draft` (brouillon, recommandé pour vérification) ou `release` (publiée directement). |

### Hooks automatiques de pré-compilation (`config/nativephp.php`)

Avant la compilation finale, NativePHP exécute automatiquement les scripts configurés dans `prebuild` :
```php
'prebuild' => [
    'npm run build',         // Compile les assets Vite et styles Tailwind
    'php artisan optimize',  // Met en cache routes, configs et vues
],
```

---

## 3. Procédure de Publication Pas à Pas

### Étape 1 : Préparer la version
1. **Incrémenter la version** :
   Modifiez `NATIVEPHP_APP_VERSION` dans votre `.env` (et `config/nativephp.php` si nécessaire) :
   ```dotenv
   NATIVEPHP_APP_VERSION=1.0.1
   ```
   > [!IMPORTANT]
   > Les migrations de base de données SQLite ne s'exécutent sur les postes utilisateurs **que si le numéro de version change**. Ne sautez jamais cette étape !

2. **Mettre à jour le changelog** :
   Documentez les nouveautés et correctifs dans [CHANGELOG.md](file:///c:/Users/D3vOs/Projets/Laravel/Web/links-vault/CHANGELOG.md).

3. **Vérifier les tests** :
   ```bash
   php artisan test --compact
   ```

---

### Étape 2 : Créer la Release Brouillon sur GitHub *(Recommandé)*
1. Allez sur GitHub : `https://github.com/baguidevos/links-vault/releases/new`
2. **Tag version** : Indiquez la version préfixée de `v` (ex: `v1.0.1`).
3. **Release title** : Titre de votre version (ex: `LinksVault v1.0.1 - Correctifs & Améliorations`).
4. **Description** : Copiez le contenu de votre changelog.
5. Cochez **« Save draft »** (Enregistrer comme brouillon).

---

### Étape 3 : Compiler et Publier

Deux méthodes sont disponibles :

#### Méthode A : Publication locale depuis votre terminal (Windows)

Assurez-vous que `GITHUB_TOKEN` est défini dans votre `.env`, puis lancez :

```powershell
# Commande Artisan directe
php artisan native:publish win

# Ou via le raccourci Composer configuré
composer run desktop:publish
```

**Ce qui se passe :**
1. Les assets Vite sont compilés (`npm run build`).
2. L'application Laravel est optimisée (`php artisan optimize`).
3. Electron empaquette l'application en installateur Windows (`.exe` NSIS).
4. Les fichiers générés (`LinksVault-Setup-1.0.1.exe`, fichiers `.blockmap` et `latest.yml`) sont téléversés directement sur votre release GitHub brouillon.

#### Méthode B : Publication automatique via GitHub Actions (CI/CD)

Le workflow [desktop-release.yml](file:///c:/Users/D3vOs/Projets/Laravel/Web/links-vault/.github/workflows/desktop-release.yml) permet de compiler et publier sans charger votre machine locale.

**Option 1 - Par Git Tag :**
```bash
git tag v1.0.1
git push origin v1.0.1
```

**Option 2 - Déclenchement manuel :**
1. Allez dans l'onglet **Actions** de votre dépôt GitHub.
2. Sélectionnez **« Desktop Release »**.
3. Cliquez sur **« Run workflow »**, choisissez la branche et le statut (`draft` ou `release`).

---

### Étape 4 : Publier la Release sur GitHub
Une fois les fichiers uploadés (par la commande locale ou par GitHub Actions) :
1. Ouvrez votre release brouillon sur GitHub.
2. Vérifiez que les installateurs (`.exe`) et le manifeste `latest.yml` sont bien attachés.
3. Cliquez sur **« Publish release »**.
4. Dès ce moment, tous les clients Desktop existants détecteront la mise à jour !

---

## 4. Expérience Utilisateur & Fonctionnalités In-App

### Détection automatique
- En production, chaque fois qu'un utilisateur lance LinksVault, l'AutoUpdater interroge GitHub Releases.
- Si une version supérieure est trouvée, le téléchargement démarre en tâche de fond sans interrompre le travail de l'utilisateur.

### Contrôle manuel dans l'interface
Dans LinksVault Desktop, ouvrez le menu **Paramètres > Synchronisation Cloud & Desktop** (`/app/settings/sync`) :
- **Badge de version** : Affiche la version installée (ex: `v1.0.0`).
- **Bouton « Rechercher une mise à jour »** : Déclenche immédiatement une vérification via `AutoUpdater::checkForUpdates()`.
- **Bouton « Redémarrer & Installer »** : Apparaît dès que la mise à jour est téléchargée. Il appelle `AutoUpdater::quitAndInstall()` pour appliquer la mise à jour instantanément.

### Menus Système
- **Barre de menus native** : Menu *Aide > Mises à jour & Synchronisation...*
- **Barre des tâches / Tray Icon** : Clic droit sur l'icône LinksVault > *Mises à jour & Synchronisation*.

---

## 5. Signature de Code (Code Signing)

Sans signature de code, Windows affiche un avertissement bleu *« Windows a protégé votre ordinateur (SmartScreen) »*.

### Options pour Windows :
1. **Azure Trusted Signing (Recommandé par NativePHP)** :
   Service cloud Microsoft évitant d'acheter une clé matérielle HSM physique.
   Renseignez dans votre `.env` :
   ```dotenv
   AZURE_TENANT_ID=...
   AZURE_CLIENT_ID=...
   AZURE_CLIENT_SECRET=...
   NATIVEPHP_AZURE_PUBLISHER_NAME="Votre Nom ou Entreprise"
   NATIVEPHP_AZURE_ENDPOINT=https://eus.codesigning.azure.net/
   NATIVEPHP_AZURE_CERTIFICATE_PROFILE_NAME=...
   NATIVEPHP_AZURE_CODE_SIGNING_ACCOUNT_NAME=...
   ```
2. **Certificat traditionnel (OV/EV)** :
   Utilise `signtool.exe` lors de la compilation.

> [!NOTE]
> La signature de code n'est pas obligatoire pour tester ou pour un usage interne : l'utilisateur peut cliquer sur *« Informations complémentaires »* puis *« Exécuter quand même »*.

---

## 6. Guide de Dépannage (FAQ)

### L'application ne détecte pas la mise à jour
1. **Êtes-vous en mode développement (`php artisan native:serve`) ?**
   L'AutoUpdater est **désactivé en mode dev** pour éviter d'écraser votre code source. Il ne s'exécute que dans l'exécutable compilé (`php artisan native:build`).
2. **La version a-t-elle bien été incrémentée ?**
   Si la release GitHub a le même numéro de version que l'application locale, l'AutoUpdater conclut que l'application est déjà à jour.
3. **La release est-elle publiée ?**
   Si la release est toujours en *Draft*, l'AutoUpdater public ne la verra pas (sauf si `GITHUB_RELEASE_TYPE=draft` est configuré avec un token).

### Les migrations SQLite ne se sont pas appliquées
- NativePHP compare le numéro de version stocké dans l'AppData avec la version du binaire. Si vous publiez du nouveau code avec des migrations sans changer `NATIVEPHP_APP_VERSION`, NativePHP ne relancera pas `php artisan migrate`.

### Mon dépôt GitHub est privé
- Par défaut, GitHub Releases est public. Si votre dépôt est privé, ajoutez un Personal Access Token avec accès en lecture seule dans `GITHUB_AUTOUPDATE_TOKEN` dans votre configuration.
