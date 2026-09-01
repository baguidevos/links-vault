<?php

namespace App\Models;

use App\Concerns\AddUserId;
use App\Enums\ContentType;
use App\Enums\FolderVisibility;
use App\Enums\LinkHealthStatus;
use App\Enums\LinkVisibility;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Link extends Model
{
    use AddUserId;

    protected $fillable = [
        'user_id',
        'team_id',
        'url',
        'url_hash',
        'tags',
        'title',
        'description',
        'content_type',
        'metadata',
        'ai_summary',
        'ai_summary_status',
        'objective',
        'category_id',
        'folder_id',
        'visibility',
        'favicon_url',
        'thumbnail_url',
        'is_favorite',
        'is_archived',
        'visit_count',
        'last_visited_at',
        'http_status',
        'health_status',
        'last_health_checked_at',
        'health_error',
        'redirect_url',
        'embedding',
        'embedding_model',
        'embedding_generated_at',
    ];

    protected $casts = [
        'url' => 'string',
        'content_type' => ContentType::class,
        'visibility' => LinkVisibility::class,
        'health_status' => LinkHealthStatus::class,
        'metadata' => 'array',
        'embedding' => 'array',
        'is_favorite' => 'boolean',
        'is_archived' => 'boolean',
        'visit_count' => 'integer',
        'http_status' => 'integer',
        'last_visited_at' => 'datetime',
        'last_health_checked_at' => 'datetime',
        'embedding_generated_at' => 'datetime',
    ];

    public function hasEmbedding(): bool
    {
        return ! empty($this->embedding) && is_array($this->embedding);
    }

    /**
     * Construire le texte enrichi pour l'indexation sémantique.
     */
    public function getEmbeddingText(): string
    {
        $parts = [];

        if (! empty($this->title)) {
            $parts[] = "Titre: {$this->title}";
        }

        if (! empty($this->description)) {
            $parts[] = "Description: {$this->description}";
        }

        if (! empty($this->ai_summary)) {
            $parts[] = "Résumé IA: {$this->ai_summary}";
        }

        if (! empty($this->tags)) {
            $tagsStr = is_array($this->tags) ? implode(', ', $this->tags) : (string) $this->tags;
            $parts[] = "Tags: {$tagsStr}";
        }

        if (! empty($this->objective)) {
            $parts[] = "Objectif: {$this->objective}";
        }

        if ($this->relationLoaded('category') && $this->category) {
            $parts[] = "Catégorie: {$this->category->name}";
        }

        if ($this->relationLoaded('folder') && $this->folder) {
            $parts[] = "Dossier: {$this->folder->name}";
        }

        return implode("\n", $parts);
    }

    public function isHealthy(): bool
    {
        return $this->health_status === LinkHealthStatus::Healthy;
    }

    public function isBroken(): bool
    {
        return $this->health_status === LinkHealthStatus::Broken;
    }

    public function isRedirect(): bool
    {
        return $this->health_status === LinkHealthStatus::Redirect;
    }

    /**
     * Obtenir les métadonnées sous forme de tableau garanti.
     *
     * @return array<string, mixed>
     */
    public function getSafeMetadata(): array
    {
        if (is_array($this->metadata)) {
            return $this->metadata;
        }

        if (is_string($this->metadata) && ! empty($this->metadata)) {
            $decoded = json_decode($this->metadata, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Utilisateurs ayant un accès restreint à ce lien.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'link_user')
            ->withTimestamps();
    }

    /**
     * Scope pour filtrer les liens accessibles à un utilisateur.
     *
     * Hiérarchie de visibilité :
     * 1. Le propriétaire de l'équipe active voit tout.
     * 2. Sinon, un lien est visible si :
     *    a. Il a été créé par l'utilisateur (user_id).
     *    b. Sa visibilité est « team ».
     *    c. Sa visibilité est « restricted » ET l'utilisateur est dans link_user.
     *    d. Il est dans un dossier « team » ou « restricted » dont l'utilisateur est membre.
     *    e. Il a été partagé via LinkShare.
     *
     * Les liens « private » d'un autre utilisateur ne sont JAMAIS visibles,
     * même s'ils se trouvent dans un dossier dont l'invité est propriétaire.
     */
    public function scopeAccessibleForUser(Builder $query, User $user): Builder
    {
        $tenant = class_exists(Filament::class) ? Filament::getTenant() : null;

        if ($tenant && $user->ownsTeam($tenant)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            // 1. Liens créés par l'utilisateur
            $q->where('user_id', $user->id)
                // 2. Liens avec visibilité « team »
                ->orWhere(function (Builder $teamQ) {
                    $teamQ->where('visibility', LinkVisibility::Team->value)
                        ->orWhere('visibility', LinkVisibility::Team);
                })
                // 3. Liens avec visibilité « restricted » ET utilisateur assigné
                ->orWhere(function (Builder $restrictedQ) use ($user) {
                    $restrictedQ->where(function ($sq) {
                        $sq->where('visibility', LinkVisibility::Restricted->value)
                            ->orWhere('visibility', LinkVisibility::Restricted);
                    })->whereHas('members', fn (Builder $m) => $m->where('users.id', $user->id));
                })
                // 4. Liens dans un dossier team/restricted accessible (hors private)
                ->orWhereHas('folder', function (Builder $folderQuery) use ($user) {
                    $folderQuery->where(function (Builder $fq) use ($user) {
                        $fq->where('visibility', FolderVisibility::Team->value)
                            ->orWhere('visibility', FolderVisibility::Team)
                            ->orWhere(function (Builder $rq) use ($user) {
                                $rq->where(function ($sq) {
                                    $sq->where('visibility', FolderVisibility::Restricted->value)
                                        ->orWhere('visibility', FolderVisibility::Restricted);
                                })->whereHas('members', fn (Builder $m) => $m->where('users.id', $user->id));
                            });
                    });
                })
                // 5. Liens partagés via LinkShare
                ->orWhereHas('shares', function (Builder $shareQuery) use ($user) {
                    $shareQuery->where('recipient_user_id', $user->id)->valid();
                });
        });
    }

    /**
     * Les partages de ce lien.
     */
    public function shares(): HasMany
    {
        return $this->hasMany(LinkShare::class);
    }

    public function getYoutubeVideoId(): ?string
    {
        if (empty($this->url)) {
            return null;
        }

        $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i';

        if (preg_match($pattern, $this->url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Génère l'URL d'intégration (embed) pour l'iframe.
     */
    public function getYoutubeEmbedUrl(): ?string
    {
        $id = $this->getYoutubeVideoId();

        return $id ? "https://www.youtube.com/embed/{$id}" : null;
    }

    public function getYoutubeThumbnailUrl(): ?string
    {
        $id = $this->getYoutubeVideoId();

        return $id ? "https://img.youtube.com/vi/{$id}/0.jpg" : null;
    }

    /**
     * Vérifie si un utilisateur a le droit de modifier la visibilité de ce lien.
     *
     * Règles :
     * 1. Le bouton/action ne doit s'afficher que sur l'espace d'appartenance du lien (l'espace du créateur).
     * 2. L'utilisateur doit être le créateur du lien ou le propriétaire de l'espace.
     * 3. Si c'est un invité, il doit avoir le rôle éditeur (sur le dossier du lien).
     */
    public function canChangeVisibility(User $user, ?Model $tenant = null): bool
    {
        $currentTenant = $tenant ?: (class_exists(Filament::class) ? Filament::getTenant() : null);

        // 1. Ne s'affiche que sur l'espace du créateur / d'origine du lien
        if ($currentTenant && $this->team_id && (int) $this->team_id !== (int) $currentTenant->id) {
            return false;
        }

        // 2. Créateur du lien
        if ($this->user_id === $user->id) {
            return true;
        }

        // 3. Propriétaire de l'équipe
        if ($this->team && $user->ownsTeam($this->team)) {
            return true;
        }

        // 4. Invité ayant le rôle éditeur sur le dossier associé
        if ($this->folder && $this->folder->canBeEditedBy($user)) {
            return true;
        }

        return false;
    }
}
