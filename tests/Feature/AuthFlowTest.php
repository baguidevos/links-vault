<?php

use App\Models\Team;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Auth\Pages\Register;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('user can view registration page', function () {
    $this->get('/app/register')
        ->assertOk();
});

test('user can register via registration page', function () {
    Livewire::test(Register::class)
        ->fillForm([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('users', [
        'email' => 'john@example.com',
    ]);
});

test('user can view login page', function () {
    $this->get('/app/login')
        ->assertOk();
});

test('user can authenticate via login page', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'password123',
    ]);

    Livewire::test(Login::class)
        ->fillForm([
            'email' => 'jane@example.com',
            'password' => 'password123',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
});

test('authenticated user accessing /app is redirected to their tenant', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'name' => 'Jane Team',
        'slug' => 'jane-team',
        'is_personal' => true,
    ]);
    $user->teams()->attach($team->id, ['role' => 'owner']);

    $this->actingAs($user)
        ->get('/app')
        ->assertRedirect("/app/{$team->slug}");
});

test('newly registered user can access their tenant dashboard and pages', function () {
    Livewire::test(Register::class)
        ->fillForm([
            'name' => 'New User',
            'email' => 'newuser2@example.com',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'newuser2@example.com')->first();
    $team = $user->teams()->first();
    expect($team)->not->toBeNull();

    $response = $this->actingAs($user)->get("/app/{$team->slug}");
    $response->assertOk();
});
