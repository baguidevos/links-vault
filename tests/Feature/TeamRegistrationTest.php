<?php

declare(strict_types=1);

use App\Listeners\CreatePersonalTeam;
use App\Models\User;
use Filament\Auth\Events\Registered as FilamentRegistered;
use Illuminate\Auth\Events\Registered as LaravelRegistered;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('registering a user creates exactly one personal team without duplicates', function () {
    $user = User::factory()->create([
        'name' => 'Alice Martin',
        'email' => 'alice@example.com',
    ]);

    // Simuler l'événement d'enregistrement Filament
    event(new FilamentRegistered($user));

    $user->refresh();

    // Vérifier qu'une seule équipe personnelle a été créée
    expect($user->teams()->count())->toBe(1)
        ->and($user->personalTeam())->not->toBeNull()
        ->and($user->personalTeam()->name)->toBe('Espace de Alice Martin')
        ->and($user->current_team_id)->toBe($user->personalTeam()->id);
});

test('subsequent registration events do not create duplicate personal teams (idempotence)', function () {
    $user = User::factory()->create([
        'name' => 'Bob Dupont',
        'email' => 'bob@example.com',
    ]);

    // Déclenchement consécutif des deux événements (Filament et Laravel)
    event(new FilamentRegistered($user));
    event(new LaravelRegistered($user));

    // Appel direct du listener pour tester la robustesse
    app(CreatePersonalTeam::class)->handle(new FilamentRegistered($user));

    $user->refresh();

    // L'utilisateur ne doit toujours avoir qu'une seule équipe
    expect($user->teams()->count())->toBe(1)
        ->and($user->teams()->where('is_personal', true)->count())->toBe(1);
});
