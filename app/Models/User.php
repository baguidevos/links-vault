<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Concerns\TeamConcerns\HasTeams;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use LaravelDaily\FilaTeams\Contracts\HasTeamMembership;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasTeamMembership
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasTeams, Notifiable;

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
        ];
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
