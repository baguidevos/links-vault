# 🧠 Documentation Technique & Guide d'Utilisation : Recherche Sémantique & Embeddings IA (Vector Search)

> **Module :** Recherche Sémantique & Vector Search par Intelligence Artificielle  
> **Package :** Laravel AI SDK (`laravel/ai`)  
> **Service Central :** [`App\Services\SemanticSearchService`](file:///c:/Users/D3vOs/Projets/Laravel/Web/links-vault/app/Services/SemanticSearchService.php)  
> **Job Asynchrone :** [`App\Jobs\GenerateLinkEmbeddingJob`](file:///c:/Users/D3vOs/Projets/Laravel/Web/links-vault/app/Jobs/GenerateLinkEmbeddingJob.php)  
> **Commande Artisan :** `php artisan links:generate-embeddings`  
> **Tests Pest :** [`tests/Feature/SemanticSearchTest.php`](file:///c:/Users/D3vOs/Projets/Laravel/Web/links-vault/tests/Feature/SemanticSearchTest.php)

---

## 1. Principe & Fonctionnement

La recherche sémantique permet de trouver des liens en interrogeant le système en **langage naturel** par **intention, sujet ou idée globale** (ex: *« tutoriels pour déployer laravel sur vps »* ou *« articles sur le design system »*), sans avoir besoin de taper les mots-clés exacts du titre ou de l'URL.

### Pipeline d'Indexation & Recherche :
```
[Nouveau Lien / Résumé IA] ➔ [Génération Embedding (Laravel\Ai)] ➔ [Vecteur sauvegardé dans links.embedding]
                                                                                │
[Requête Utilisateur "Idée"] ➔ [Embedding Requête] ➔ [Similarité Cosinus] ➔ [Top Liens Pertinents (90%+)]
```

---

## 2. Architecture Technique & Modèle de Données

### Migration & Colonnes (`links`) :
- `embedding` (`longText` / `json`) : vecteur de dimension $N$ (ex: 1536 floats pour `text-embedding-3-small` / OpenAI, Voyage, Gemini).
- `embedding_model` (`string`) : nom du modèle utilisé.
- `embedding_generated_at` (`timestamp`) : date et heure de l'indexation.

### Modèle [`Link.php`](file:///c:/Users/D3vOs/Projets/Laravel/Web/links-vault/app/Models/Link.php) :
- `hasEmbedding(): bool` : teste si le lien dispose déjà d'un vecteur valide.
- `getEmbeddingText(): string` : construit le contexte textuel enrichi (Titre + Description + Tags + Résumé IA + Objectif + Dossier/Catégorie).

---

## 3. Service Centralisé : `SemanticSearchService`

### Méthodes Clés :
```php
$service = app(SemanticSearchService::class);

// 1. Indexer un lien spécifique
$service->indexLink($link, force: false);

// 2. Recherche sémantique par intention
$results = $service->search(
    query: 'guide conteneurisation docker',
    team: $currentTeam,
    limit: 10,
    minSimilarity: 0.25
);

// Chaque résultat dispose de :
// - $link->similarity_score (ex: 0.89)
// - $link->similarity_percentage (ex: 89%)
```

### Calcul de Similarité Cosinus :
Le calcul est vectoriel normalisé :
$$\text{Sim}(A, B) = \frac{A \cdot B}{\|A\|_2 \times \|B\|_2}$$

---

## 4. Tâches de Fond & Automatisation

1. **Job Asynchrone ([`GenerateLinkEmbeddingJob.php`](file:///c:/Users/D3vOs/Projets/Laravel/Web/links-vault/app/Jobs/GenerateLinkEmbeddingJob.php))** :
   - Déclenché automatiquement à la création d'un lien (`CreateLinkAction`).
   - Ré-exécuté automatiquement après génération du résumé IA (`GenerateAiSummaryAction`) avec le contexte enrichi.
2. **Commande CLI Artisan ([`GenerateEmbeddingsCommand.php`](file:///c:/Users/D3vOs/Projets/Laravel/Web/links-vault/app/Console/Commands/GenerateEmbeddingsCommand.php))** :
   ```bash
   # Indexer tous les liens non indexés
   php artisan links:generate-embeddings

   # Forcer la réindexation complète pour une équipe spécifique
   php artisan links:generate-embeddings --team=1 --force
   ```

---

## 5. Interface Utilisateur Filament (`ListLinks`)

Accessible directement depuis la barre d'outils de la page **Liens** :
- Bouton d'en-tête **« 🧠 Recherche IA »**.
- Modale de saisie en langage naturel avec sélecteur de précision (*Large 15%+*, *Équilibré 25%+*, *Strict 40%+*).
- Modale interactive de résultats [`semantic-search-results.blade.php`](file:///c:/Users/D3vOs/Projets/Laravel/Web/links-vault/resources/views/filament/modals/semantic-search-results.blade.php) affichant :
  - Badges de correspondance en pourcentage (ex: `🎯 94%`).
  - Extraits du résumé IA et métadonnées.
  - Boutons directs 1-clic pour **« Voir la fiche »** ou **« Ouvrir »** le lien.
