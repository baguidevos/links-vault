<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use LaravelDaily\FilaTeams\Models\Team;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PlanSeeder::class);

        $webmaster = User::firstOrCreate(
            ['email' => 'webmaster@gmail.com'],
            [
                'name' => 'Webmaster',
                'password' => Hash::make('password'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        if (! $webmaster->is_admin) {
            $webmaster->update(['is_admin' => true]);
        }

        if ($webmaster->teams()->count() === 0) {
            $team = Team::firstOrCreate(
                ['slug' => 'webmaster-team'],
                [
                    'name' => "Webmaster's Team",
                    'is_personal' => true,
                ]
            );

            if (! $webmaster->belongsToTeam($team)) {
                $webmaster->teams()->attach($team->id, ['role' => 'owner']);
            }

            $webmaster->update(['current_team_id' => $team->id]);
        }
    }
}
