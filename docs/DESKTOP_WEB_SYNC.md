# 🔄 Spécification & Architecture : Synchronisation Desktop (NativePHP) ↔ Web (Cloud)

> **Module :** Synchronisation bidirectionnelle Multi-Plateforme (Desktop SQLite ↔ Web Cloud)  
> **Composants :** NativePHP (Electron / SQLite local) & Laravel Web API (Sanctum / MySQL ou PostgreSQL)  
> **Protocole :** REST Delta Sync (Local-First avec Source de Vérité Centrale)  
> **Documentation :** `docs/DESKTOP_WEB_SYNC.md`  
> **Branche :** `feature/desktop-web-sync`

---

## 1. Contexte & Problématique

### 1.1 Deux environnements distincts
LinksVault est conçu pour fonctionner sur deux environnements complémentaires :
1. **Application Web hébergée** :
   - Hébergée sur un serveur cloud / VPS.
   - Base de données centrale (MySQL ou PostgreSQL).
   - Multi-tenant (équipes, abonnements Stripe/Subbase, utilisateurs multiples).
2. **Application Desktop Native (NativePHP / Electron)** :
   - Installée sur la machine de l'utilisateur (Windows / macOS).
   - Moteur PHP embarqué et base de données locale SQLite (`nativephp.sqlite`).
   - Fonctionnement **Local-First** : ultra-rapide, consultations et ajouts hors-ligne, persistance locale.

### 1.2 Le défi de la cohabitation
Sans couche de synchronisation, les données restent cloisonnées :
- Un lien ajouté dans l'application Desktop n'apparaît pas sur l'application Web.
- Un lien sauvegardé depuis l'extension Chrome ou l'interface Web n'est pas répercuté sur le Desktop.
- Des conflits d'identifiants auto-incrémentés (`id: 1, 2, 3...`) se produiraient si l'on fusionnait naïvement les bases de données.

---

## 2. Principes Fondamentaux de la Synchronisation

Pour garantir la résilience, la rapidité et l'intégrité des données, LinksVault adopte les 4 piliers suivants :

```
┌──────────────────────────────────────────────┐
│            Principes Directeurs              │
├──────────────────────┬───────────────────────┤
│ 1. Local-First       │ L'application locale  │
│                      │ reste réactive à 100% │
│                      │ même sans connexion.  │
├──────────────────────┼───────────────────────┤
│ 2. Serveur Maître    │ Le Web Cloud est la   │
│    (Truth Authority) │ source de vérité pour │
│                      │ arbitrer les conflits │
├──────────────────────┼───────────────────────┤
│ 3. Identifiants UUID │ Aucun couplage sur    │
│                      │ les clés primaires ID │
├──────────────────────┼───────────────────────┤
│ 4. Delta Sync        │ Seuls les deltas      │
│    (Horodatés)       │ modifiés depuis la    │
│                      │ dernière synchro      │
│                      │ sont transmis.        │
└──────────────────────┴───────────────────────┘
```

---

## 3. Architecture des Données

### 3.1 Identifiants Universels (`uuid`)
Afin de décorréler les bases SQLite locales et MySQL/Postgres distantes, chaque entité synchronisée dispose d'une colonne `uuid` (`CHAR(36)` unique) :
- `links.uuid`
- `folders.uuid`
- `categories.uuid`
- `tags.uuid`

Lors d'un échange réseau, toutes les relations utilisent les UUID :
- Au lieu de `folder_id = 4`, la charge utile contient `folder_uuid = "a1b2c3d4-..."`.
- L'environnement récepteur résout le `folder_uuid` vers son `folder_id` local respectif.

### 3.2 Gestion des Suppressions : Table `sync_tombstones`
Pour propager les suppressions sans imposer systématiquement des contraintes de SoftDeletes qui fausseraient les index d'unicité (ex: `['user_id', 'url_hash']`), une table de "tombstones" (pierres tombales) est utilisée :
```sql
CREATE TABLE sync_tombstones (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    team_id BIGINT NOT NULL,
    entity_type VARCHAR(50) NOT NULL, -- 'link', 'folder', 'category', 'tag'
    entity_uuid CHAR(36) NOT NULL,
    deleted_at TIMESTAMP NOT NULL,
    INDEX (team_id, deleted_at)
);
```
Dès qu'un lien ou dossier est supprimé (sur le Desktop ou sur le Web), un événement Eloquent insère une entrée dans `sync_tombstones`.

### 3.3 Suivi de l'État de Synchronisation : Table `sync_settings` (Desktop)
L'application Desktop retient son état local dans une table `sync_settings` ou via le cache persistant :
- `server_url` : URL de l'instance Web (ex: `https://linksvault.app`).
- `api_token` : Token Sanctum délivré par le serveur Web.
- `team_id` : Identifiant ou UUID de l'équipe distante synchronisée.
- `last_synced_at` : Date et heure de la dernière synchronisation réussie (ex: `2026-09-06T00:00:00Z`).
- `sync_status` : `'idle' | 'syncing' | 'error'`.
- `last_error` : Dernier message d'erreur si la synchro échoue.

---

## 4. Protocole d'Échange API (Delta Sync)

Deux endpoints dédiés et protégés par `auth:sanctum` sont exposés sur le Web :

### 4.1 Étape 1 : PUSH (Desktop ➔ Web)
Le Desktop transmet au serveur Web toutes les modifications locales survenues depuis `last_synced_at` :

`POST /api/v1/sync/push`
```json
{
  "team_uuid": "e5f6g7h8-...",
  "last_synced_at": "2026-09-05T14:30:00Z",
  "entities": {
    "folders": [
      {
        "uuid": "f1-uuid",
        "name": "Documentation IA",
        "slug": "documentation-ia",
        "color": "#6366f1",
        "icon": "heroicon-o-book-open",
        "updated_at": "2026-09-05T15:00:00Z"
      }
    ],
    "categories": [],
    "tags": [
      { "name": "laravel", "slug": "laravel" }
    ],
    "links": [
      {
        "uuid": "l1-uuid",
        "url": "https://laravel.com/docs",
        "title": "Laravel Documentation",
        "description": "Official docs",
        "folder_uuid": "f1-uuid",
        "category_uuid": null,
        "tags": ["laravel"],
        "is_favorite": true,
        "updated_at": "2026-09-05T15:10:00Z"
      }
    ],
    "deleted": [
      { "entity_type": "link", "uuid": "l2-uuid", "deleted_at": "2026-09-05T15:05:00Z" }
    ]
  }
}
```

**Traitement côté Web :**
1. Pour chaque entité, le serveur compare le `updated_at` entrant avec le `updated_at` existant en base (**Last-Write-Wins**).
2. Si le `updated_at` entrant est plus récent, les données sont mises à jour ou créées.
3. Les suppressions listées dans `deleted` sont appliquées et archivées dans `sync_tombstones`.
4. Le serveur renvoie un accusé de réception (`success: true`, et les UUIDs confirmés).

---

### 4.2 Étape 2 : PULL (Web ➔ Desktop)
Le Desktop récupère toutes les modifications intervenues sur le Web depuis sa dernière synchro :

`GET /api/v1/sync/pull?team_uuid=e5f6g7h8-...&since=2026-09-05T14:30:00Z`

**Réponse du serveur Web :**
```json
{
  "server_time": "2026-09-06T00:15:00Z",
  "entities": {
    "folders": [...],
    "categories": [...],
    "tags": [...],
    "links": [...],
    "deleted": [
      { "entity_type": "link", "uuid": "l3-uuid", "deleted_at": "2026-09-05T18:00:00Z" }
    ]
  }
}
```

**Traitement côté Desktop :**
1. Le Desktop applique les créations / mises à jour reçues dans son SQLite local.
2. Il applique les suppressions locales pour chaque élément présent dans `deleted`.
3. Il met à jour son horodatage local : `last_synced_at = server_time`.

---

## 5. Résolution des Conflits (Conflict Strategy)

La stratégie adoptée est **LWW (Last-Write-Wins)** au niveau de l'enregistrement avec priorité serveur en cas d'égalité stricte :
1. Si l'enregistrement distant a un `updated_at` plus récent que l'enregistrement local ➔ La version distante écrase la version locale.
2. Si l'enregistrement local a un `updated_at` plus récent ➔ La version locale est acceptée par le serveur.
3. Si un conflit concerne un lien supprimé d'un côté et modifié de l'autre ➔ La suppression l'emporte par défaut afin d'éviter les réapparitions intempestives (zombie records).

---

## 6. Déclencheurs & Expérience Utilisateur (Desktop)

Dans l'application NativePHP Desktop, la synchronisation est pilotée par plusieurs déclencheurs :

1. **Au démarrage de l'application :**
   - Dès le boot de `NativeAppServiceProvider`, un job d'arrière-plan vérifie la connectivité réseau et lance un cycle de synchronisation silencieux.
2. **Périodique (Planificateur) :**
   - Un job récurrent toutes les 5 à 15 minutes en tâche de fond.
3. **Manuel via l'interface :**
   - Un composant d'état dans la barre supérieure Filament / Livewire :
     - 🟢 *Synchronisé il y a 2 min*
     - 🔄 *Synchronisation en cours...*
     - 🔴 *Déconnecté / Erreur de synchro*
     - Bouton d'action « *Synchroniser maintenant* ».
4. **Reconnexion réseau (Online Detection) :**
   - Écoute de l'événement JavaScript `navigator.onLine` / `window.addEventListener('online')` pour réenclencher automatiquement la synchro dès que la machine retrouve Internet.

---

## 7. Sécurité & Authentification

- **Chiffrement de transport :** Tous les échanges s'effectuent obligatoirement en HTTPS.
- **Authentification Sanctum :** Le token API généré sur le Web est restreint à l'utilisateur et expire selon la politique du serveur.
- **Isolation Multi-tenant :** Chaque requête valide que l'utilisateur a bien les droits sur l'équipe (`team_id`) demandée.
- **Chiffrement des URLs :** Le chiffrement applicatif d'URL de LinksVault (`casts => ['url' => 'encrypted']`) s'exécute de façon transparente de part et d'autre grâce au cycle de vie d'Eloquent.
