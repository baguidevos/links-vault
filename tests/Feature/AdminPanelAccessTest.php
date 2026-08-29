<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelDaily\FilaTeams\Models\Team;

uses(RefreshDatabase::class);

test('a non-admin user cannot access the admin panel', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

test('an admin user can access the admin panel', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk();
});

test('both admin and non-admin users can access the app panel', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Mon Espace',
        'slug' => 'mon-espace',
        'personal_team' => true,
    ]);
    $user->teams()->attach($team->id, ['role' => 'owner']);

    $this->actingAs($user)
        ->get("/app/{$team->slug}")
        ->assertOk();
});
