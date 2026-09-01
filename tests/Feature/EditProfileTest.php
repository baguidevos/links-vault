<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\EditProfile;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('authenticated user can view their profile edit page in tenant context', function () {
    $user = User::factory()->create([
        'name' => 'Alice Dev',
        'email' => 'alice@example.com',
        'locale' => 'fr',
        'timezone' => 'Europe/Paris',
    ]);

    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Alice Team',
        'slug' => 'alice-team',
        'is_personal' => true,
    ]);

    $this->actingAs($user);
    Filament::setTenant($team);

    Livewire::test(EditProfile::class)
        ->assertSuccessful()
        ->assertSchemaStateSet([
            'name' => 'Alice Dev',
            'email' => 'alice@example.com',
            'locale' => 'fr',
            'timezone' => 'Europe/Paris',
        ]);
});

test('user can update profile personal information', function () {
    $user = User::factory()->create([
        'name' => 'Bob Initial',
        'email' => 'bob@example.com',
        'locale' => 'en',
        'timezone' => 'UTC',
    ]);

    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Bob Team',
        'slug' => 'bob-team',
        'is_personal' => true,
    ]);

    $this->actingAs($user);
    Filament::setTenant($team);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'name' => 'Bob Updated',
            'email' => 'bob@example.com',
            'locale' => 'fr',
            'timezone' => 'Europe/Paris',
        ])
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('Bob Updated')
        ->and($user->locale)->toBe('fr')
        ->and($user->timezone)->toBe('Europe/Paris');
});

test('user can update their password with valid current password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('OldPassword123!'),
    ]);

    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Charlie Team',
        'slug' => 'charlie-team',
        'is_personal' => true,
    ]);

    $this->actingAs($user);
    Filament::setTenant($team);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'name' => $user->name,
            'email' => $user->email,
            'currentPassword' => 'OldPassword123!',
            'password' => 'NewSecretPass123!',
            'passwordConfirmation' => 'NewSecretPass123!',
        ])
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();
    expect(Hash::check('NewSecretPass123!', $user->password))->toBeTrue();
});
