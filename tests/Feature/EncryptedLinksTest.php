<?php

declare(strict_types=1);

use App\Models\Link;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('saving a link encrypts the url in database and decrypts it when retrieved through eloquent', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Security Team',
        'slug' => 'security-team',
        'is_personal' => true,
    ]);

    $plainUrl = 'https://super-secret-vault.com/internal-docs?key=secret-token-123';
    $urlHash = hash('sha256', $plainUrl);

    $link = Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'title' => 'Secret Vault Docs',
        'url' => $plainUrl,
        'url_hash' => $urlHash,
    ]);

    // Vérification directe en base de données brute (sans passer par Eloquent)
    $rawRecord = DB::table('links')->where('id', $link->id)->first();

    expect($rawRecord->url)->not->toBe($plainUrl)
        ->and($rawRecord->url)->not->toContain('super-secret-vault')
        ->and($rawRecord->url)->not->toContain('secret-token-123')
        ->and(Crypt::decryptString($rawRecord->url))->toBe($plainUrl);

    // Vérification via le modèle Eloquent (déchiffrement transparent pour l'application)
    $freshLink = Link::find($link->id);

    expect($freshLink->url)->toBe($plainUrl);
});

test('updating the link url re-encrypts the new url', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Security Team',
        'slug' => 'security-team-2',
        'is_personal' => true,
    ]);

    $initialUrl = 'https://first-secret.org';
    $link = Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'title' => 'Initial Link',
        'url' => $initialUrl,
        'url_hash' => hash('sha256', $initialUrl),
    ]);

    $updatedUrl = 'https://second-confidential-url.net/dashboard';
    $link->update([
        'url' => $updatedUrl,
        'url_hash' => hash('sha256', $updatedUrl),
    ]);

    $rawRecord = DB::table('links')->where('id', $link->id)->first();

    expect($rawRecord->url)->not->toBe($updatedUrl)
        ->and($rawRecord->url)->not->toContain('second-confidential-url')
        ->and(Crypt::decryptString($rawRecord->url))->toBe($updatedUrl)
        ->and($link->fresh()->url)->toBe($updatedUrl);
});

test('vault:encrypt-links command encrypts unencrypted legacy urls in database', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Security Team',
        'slug' => 'security-team-3',
        'is_personal' => true,
    ]);

    $legacyPlainUrl = 'https://legacy-unencrypted-link.com/page';

    // Insertion directe en base simulant une donnée non chiffrée
    $legacyId = DB::table('links')->insertGetId([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'title' => 'Legacy Link',
        'url' => $legacyPlainUrl,
        'url_hash' => hash('sha256', $legacyPlainUrl),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Exécution de la commande de migration
    $this->artisan('vault:encrypt-links')
        ->expectsOutputToContain('Opération terminée avec succès')
        ->assertSuccessful();

    // Vérification que le lien est chiffré en base
    $rawRecord = DB::table('links')->where('id', $legacyId)->first();
    expect($rawRecord->url)->not->toBe($legacyPlainUrl)
        ->and(Crypt::decryptString($rawRecord->url))->toBe($legacyPlainUrl);

    // Vérification qu'Eloquent le déchiffre sans DecryptException
    $retrievedLink = Link::find($legacyId);
    expect($retrievedLink->url)->toBe($legacyPlainUrl);
});

test('url_hash preserves duplicate detection even when url is encrypted', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Security Team',
        'slug' => 'security-team-4',
        'is_personal' => true,
    ]);

    $url = 'https://duplicate-check.com';
    $urlHash = hash('sha256', $url);

    Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'title' => 'First Link',
        'url' => $url,
        'url_hash' => $urlHash,
    ]);

    $exists = Link::where('team_id', $team->id)
        ->where('url_hash', $urlHash)
        ->exists();

    expect($exists)->toBeTrue();
});
