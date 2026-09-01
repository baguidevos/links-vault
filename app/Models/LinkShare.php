<?php

namespace App\Models;

use App\Concerns\AddTeamId;
use App\Concerns\AddUserId;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LinkShare extends Model
{
    use AddTeamId, AddUserId, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'link_id',
        'sender_user_id',
        'recipient_user_id',
        'recipient_email',
        'recipient_name',
        'personal_message',
        'token',
        'status',
        'expires_at',
        'sent_at',
        'opened_at',
        'clicked_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
    ];

    /**
     * Relation avec le lien partagé.
     */
    public function link(): BelongsTo
    {
        return $this->belongsTo(Link::class);
    }

    /**
     * Relation avec l'utilisateur qui a partagé.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    /**
     * Relation avec l'utilisateur destinataire (si inscrit).
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /**
     * Vérifier si le partage est expiré.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Vérifier si le partage est encore valide.
     */
    public function isValid(): bool
    {
        return ! $this->isExpired() && in_array($this->status, ['sent', 'opened']);
    }

    /**
     * Marquer comme ouvert.
     */
    public function markAsOpened(): void
    {
        if (! $this->opened_at) {
            $this->update([
                'status' => 'opened',
                'opened_at' => now(),
            ]);
        }
    }

    /**
     * Marquer comme cliqué.
     */
    public function markAsClicked(): void
    {
        $this->update([
            'status' => 'clicked',
            'clicked_at' => now(),
        ]);
    }

    /**
     * Scope pour les partages envoyés par un utilisateur.
     */
    public function scopeSentBy($query, int $userId)
    {
        return $query->where('sender_user_id', $userId);
    }

    /**
     * Scope pour les partages reçus par un utilisateur.
     */
    public function scopeReceivedBy($query, int $userId)
    {
        return $query->where('recipient_user_id', $userId);
    }

    /**
     * Scope pour les partages valides (non expirés).
     */
    public function scopeValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }
}
