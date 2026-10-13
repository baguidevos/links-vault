<?php

declare(strict_types=1);

use App\Livewire\Auth\RestoreAccount;
use App\Models\Category;
use App\Models\Folder;
use App\Models\Link;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use App\Services\CloudBackup\VaultBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('login page contains link to restore account', function () {
    $this->get(route('filament.app.auth.login'))
        ->assertSuccessful()
        ->assertSee(route('filament.app.auth.restore-account'))
        ->assertSee('Restaurer un compte depuis une sauvegarde (.zip)');
});

test('guest can access the restore account page', function () {
    $this->get(route('filament.app.auth.restore-account'))
        ->assertSuccessful()
        ->assertSee('Restaurer un compte')
        ->assertSee('Nouveau mot de passe');
});

test('authenticated user is redirected away from restore account page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('filament.app.auth.restore-account'))
        ->assertRedirect();
});

test('it validates missing backup file and passwords', function () {
    Livewire::test(RestoreAccount::class)
        ->call('restore')
        ->assertHasErrors(['backup_file', 'password']);
});

test('it validates password confirmation and minimum length', function () {
    $fakeFile = UploadedFile::fake()->create('backup.zip', 100, 'application/zip');

    Livewire::test(RestoreAccount::class)
        ->set('backup_file', $fakeFile)
        ->set('password', 'short')
        ->set('password_confirmation', 'mismatch')
        ->call('restore')
        ->assertHasErrors(['password']);
});

test('it rejects an invalid or corrupted zip file', function () {
    $corruptFile = UploadedFile::fake()->createWithContent('corrupt.zip', 'not a real zip content');

    Livewire::test(RestoreAccount::class)
        ->set('backup_file', $corruptFile)
        ->set('password', 'validpassword123')
        ->set('password_confirmation', 'validpassword123')
        ->call('restore')
        ->assertHasErrors(['backup_file']);
});

test('it restores account and auto-logs in a new user with merge mode', function () {
    // 1. Create source user & data to generate a real backup
    $sourceUser = User::factory()->create([
        'name' => 'Original Owner',
        'email' => 'restore-target@example.com',
    ]);

    $sourceTeam = Team::create([
        'user_id' => $sourceUser->id,
        'name' => 'Mon Coffre Démo',
        'slug' => 'mon-coffre-demo',
        'is_personal' => true,
    ]);

    $category = Category::create([
        'user_id' => $sourceUser->id,
        'team_id' => $sourceTeam->id,
        'name' => 'Ressources Dev',
        'slug' => 'ressources-dev',
    ]);

    $folder = Folder::create([
        'team_id' => $sourceTeam->id,
        'user_id' => $sourceUser->id,
        'name' => 'Outils',
        'slug' => 'outils',
    ]);

    $tag = Tag::create([
        'user_id' => $sourceUser->id,
        'team_id' => $sourceTeam->id,
        'name' => 'Framework',
        'slug' => 'framework',
    ]);

    $link = Link::create([
        'user_id' => $sourceUser->id,
        'team_id' => $sourceTeam->id,
        'url' => 'https://pestphp.com',
        'url_hash' => hash('sha256', 'https://pestphp.com'),
        'title' => 'Pest PHP Testing Framework',
        'description' => 'An elegant PHP testing framework',
        'folder_id' => $folder->id,
        'category_id' => $category->id,
    ]);
    $link->tags()->attach($tag->id);

    // Generate real ZIP archive
    $backupService = app(VaultBackupService::class);
    $archive = $backupService->generateBackupArchive($sourceTeam, $sourceUser);

    // Delete everything in database to simulate completely fresh new setup
    Link::query()->delete();
    Category::query()->delete();
    Folder::query()->delete();
    Tag::query()->delete();
    Team::query()->delete();
    User::query()->delete();

    expect(User::where('email', 'restore-target@example.com')->exists())->toBeFalse();

    // Prepare uploaded file
    $uploadedZip = UploadedFile::fake()->createWithContent('backup.zip', $archive['content']);

    // Run Livewire restore component
    Livewire::test(RestoreAccount::class)
        ->set('backup_file', $uploadedZip)
        ->assertSet('detectedEmail', 'restore-target@example.com')
        ->assertSet('detectedLinksCount', 1)
        ->set('password', 'SecretPassword456!')
        ->set('password_confirmation', 'SecretPassword456!')
        ->call('restore')
        ->assertHasNoErrors()
        ->assertRedirect();

    // Verify user was created with Option A password
    $restoredUser = User::where('email', 'restore-target@example.com')->first();
    expect($restoredUser)->not->toBeNull()
        ->and(Hash::check('SecretPassword456!', $restoredUser->password))->toBeTrue();

    // Verify user is authenticated
    expect(Auth::check())->toBeTrue()
        ->and(Auth::id())->toBe($restoredUser->id);

    // Verify team and data were restored
    $restoredTeam = $restoredUser->personalTeam() ?? $restoredUser->teams()->first();
    expect($restoredTeam)->not->toBeNull();

    $this->assertDatabaseHas('links', [
        'team_id' => $restoredTeam->id,
        'title' => 'Pest PHP Testing Framework',
        'url_hash' => hash('sha256', 'https://pestphp.com'),
    ]);

    $restoredLink = Link::where('team_id', $restoredTeam->id)->where('title', 'Pest PHP Testing Framework')->first();
    expect($restoredLink->url)->toBe('https://pestphp.com');
});

test('it merges data into an existing account without overwriting existing links', function () {
    // 1. Create an existing user with an existing link in their team
    $existingUser = User::factory()->create([
        'name' => 'Existing User',
        'email' => 'merge-user@example.com',
        'password' => Hash::make('old-password-123'),
    ]);

    $existingTeam = Team::create([
        'user_id' => $existingUser->id,
        'name' => 'Existing Workspace',
        'slug' => 'existing-workspace',
        'is_personal' => true,
    ]);

    // Attach user as owner
    $existingUser->teams()->attach($existingTeam->id, ['role' => 'owner']);
    $existingUser->switchTeam($existingTeam);

    $existingLink = Link::create([
        'user_id' => $existingUser->id,
        'team_id' => $existingTeam->id,
        'url' => 'https://laravel.com',
        'url_hash' => hash('sha256', 'https://laravel.com'),
        'title' => 'Existing Laravel Link (Custom Title)',
    ]);

    // 2. Prepare backup containing the same link + a new link
    $backupService = app(VaultBackupService::class);
    // Create temp link to put in archive
    $secondLink = Link::create([
        'user_id' => $existingUser->id,
        'team_id' => $existingTeam->id,
        'url' => 'https://vuejs.org',
        'url_hash' => hash('sha256', 'https://vuejs.org'),
        'title' => 'Vue.js Framework',
    ]);

    $archive = $backupService->generateBackupArchive($existingTeam, $existingUser);

    // Remove the second link from DB before restore to simulate it missing in DB
    $secondLink->delete();

    // Prepare uploaded file
    $uploadedZip = UploadedFile::fake()->createWithContent('backup.zip', $archive['content']);

    // Perform restore with new password (Option A)
    Livewire::test(RestoreAccount::class)
        ->set('backup_file', $uploadedZip)
        ->set('password', 'new-brand-password-789')
        ->set('password_confirmation', 'new-brand-password-789')
        ->call('restore')
        ->assertHasNoErrors()
        ->assertRedirect();

    // Verify existing user password was updated to Option A password
    $existingUser->refresh();
    expect(Hash::check('new-brand-password-789', $existingUser->password))->toBeTrue();

    // Verify existing link title was NOT overwritten (fusionne / merge)
    $keptLink = Link::where('team_id', $existingTeam->id)->where('url_hash', hash('sha256', 'https://laravel.com'))->first();
    expect($keptLink->title)->toBe('Existing Laravel Link (Custom Title)');

    // Verify new link was restored
    $this->assertDatabaseHas('links', [
        'team_id' => $existingTeam->id,
        'title' => 'Vue.js Framework',
        'url_hash' => hash('sha256', 'https://vuejs.org'),
    ]);

    $vueLink = Link::where('team_id', $existingTeam->id)->where('title', 'Vue.js Framework')->first();
    expect($vueLink->url)->toBe('https://vuejs.org');

    // User is logged in
    expect(Auth::id())->toBe($existingUser->id);
});
