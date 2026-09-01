<?php

declare(strict_types=1);

use App\Models\CloudStorageConfig;
use App\Models\GoogleDrive;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('google drive redirect requires authentication', function () {
    $response = $this->get(route('auth.google-drive.redirect'));
    $response->assertRedirect('/login');
});

test('google drive redirect generates a valid google oauth url with prompt and drive scope', function () {
    config()->set('services.google.client_id', 'test-client-id-123.apps.googleusercontent.com');
    config()->set('services.google.client_secret', 'test-secret');

    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Mon Espace',
        'slug' => 'mon-espace',
        'is_personal' => true,
    ]);

    $response = $this->actingAs($user)->get(route('auth.google-drive.redirect', ['team_id' => $team->id]));

    $response->assertRedirect();
    $targetUrl = (string) $response->headers->get('Location');

    expect($targetUrl)->toContain('accounts.google.com/o/oauth2')
        ->and($targetUrl)->toContain('test-client-id-123')
        ->and($targetUrl)->toContain('drive.file')
        ->and($targetUrl)->toContain('prompt=consent%20select_account')
        ->and($targetUrl)->toContain('state=');
});

test('google drive disconnect removes user tokens and deactivates config', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Mon Espace',
        'slug' => 'mon-espace',
        'is_personal' => true,
    ]);

    GoogleDrive::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'access_token' => 'dummy_token',
        'refresh_token' => 'dummy_refresh',
        'expires_at' => now()->addHour(),
        'email' => 'test@gmail.com',
    ]);

    CloudStorageConfig::create([
        'team_id' => $team->id,
        'provider' => 'google_drive',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->post(route('auth.google-drive.disconnect', [
        'team_id' => $team->id,
        'team_slug' => $team->slug,
    ]));

    $response->assertRedirect();

    $this->assertDatabaseMissing('google_drives', [
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseHas('cloud_storage_configs', [
        'team_id' => $team->id,
        'provider' => 'google_drive',
        'is_active' => false,
    ]);
});
