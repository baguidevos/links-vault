<?php

declare(strict_types=1);

namespace App\Actions\TeamActions;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LaravelDaily\FilaTeams\Facades\FilaTeams;

class CreateTeam
{
    /**
     * @param  array{name: string, is_personal?: bool}  $data
     */
    public function handle(User $user, array $data): Team
    {
        return DB::transaction(function () use ($user, $data) {
            $team = Team::create([
                'name' => $data['name'],
                'is_personal' => $data['is_personal'] ?? false,
            ]);

            TeamMember::create([
                'team_id' => $team->id,
                'user_id' => $user->id,
                'role' => FilaTeams::ownerRole()->value,
            ]);

            $user->switchTeam($team);

            return $team;
        });
    }
}
