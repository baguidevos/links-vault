<?php

declare(strict_types=1);

use App\Models\Team;
use App\Models\TeamMember;
use LaravelDaily\FilaTeams\Enums\TeamPermission;
use LaravelDaily\FilaTeams\Enums\TeamRole;
use LaravelDaily\FilaTeams\Models\TeamInvitation;

return [
    'enums' => [
        'role' => TeamRole::class,
        'permission' => TeamPermission::class,
    ],
    'models' => [
        'team' => Team::class,
        'membership' => TeamMember::class,
        'invitation' => TeamInvitation::class,
    ],
    'invitation' => [
        'expires_after_days' => 7,
    ],
    // Désactivé au niveau du vendor pour éviter les conflits d'écouteurs d'événements.
    // La création est gérée de manière unique et idempotente par App\Listeners\CreatePersonalTeam.
    'create_personal_team_on_registration' => false,
];
