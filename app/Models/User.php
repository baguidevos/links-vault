<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Concerns\TeamConcerns\HasTeams;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravelcm\Subscriptions\Traits\HasPlanSubscriptions;
use LaravelDaily\FilaTeams\Contracts\HasTeamMembership;

#[Fillable(['name', 'email', 'password', 'is_admin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasTeamMembership
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPlanSubscriptions, HasTeams, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Vérifier si l'utilisateur a le droit d'accéder au panel Filament donné.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return (bool) $this->is_admin || $this->email === 'webmaster@gmail.com';
        }

        return true;
    }

    /**
     * Les liens que cet utilisateur a partagés.
     */
    public function sharedLinks(): HasMany
    {
        return $this->hasMany(LinkShare::class, 'sender_user_id');
    }

    /**
     * Les liens partagés avec cet utilisateur.
     */
    public function receivedLinks(): HasMany
    {
        return $this->hasMany(LinkShare::class, 'recipient_user_id');
    }
}
