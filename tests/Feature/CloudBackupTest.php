<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\CloudBackup;
use App\Models\CloudStorageConfig;
use App\Models\Folder;
use App\Models\Link;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use App\Services\CloudBackup\VaultBackupService;
use App\Services\CloudBackup\VaultRestoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

test('it can generate a complete vault backup zip archive with all files', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Mon Espace Pro',
        'slug' => 'mon-espace-pro',
        'is_personal' => true,
    ]);

    $category = Category::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'name' => 'Développement',
        'slug' => 'developpement',
    ]);

    $folder = Folder::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'name' => 'Laravel & PHP',
        'slug' => 'laravel-php',
    ]);

    $tag = Tag::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'name' => 'Framework',
        'slug' => 'framework',
    ]);

    $link = Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'url' => 'https://laravel.com',
        'url_hash' => hash('sha256', 'https://laravel.com'),
        'title' => 'Laravel Official Site',
        'description' => 'The PHP Framework for Web Artisans',
        'folder_id' => $folder->id,
        'category_id' => $category->id,
        'metadata' => ['author' => 'Taylor Otwell'],
    ]);
    $link->tags()->attach($tag->id);

    $backupService = app(VaultBackupService::class);
    $archive = $backupService->generateBackupArchive($team, $user);

    expect($archive['content'])->not->toBeEmpty()
        ->and($archive['filename'])->toContain('linksvault_mon-espace-pro_')
        ->and($archive['manifest']['stats']['links_count'])->toBe(1)
        ->and($archive['manifest']['stats']['folders_count'])->toBe(1);

    // Vérifier l'intérieur du zip
    $tempPath = tempnam(sys_get_temp_dir(), 'test_zip_');
    file_put_contents($tempPath, $archive['content']);

    $zip = new ZipArchive;
    expect($zip->open($tempPath))->toBeTrue();
    expect($zip->getFromName('manifest.json'))->not->toBeFalse();
    expect($zip->getFromName('links.json'))->not->toBeFalse();
    expect($zip->getFromName('bookmarks.html'))->not->toBeFalse();
    expect($zip->getFromName('links.csv'))->not->toBeFalse();
    expect($zip->getFromName('folders.json'))->not->toBeFalse();
    expect($zip->getFromName('categories.json'))->not->toBeFalse();
    expect($zip->getFromName('tags.json'))->not->toBeFalse();

    $zip->close();
    @unlink($tempPath);
});

test('it can backup a team to local storage and record in database', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Team Alpha',
        'slug' => 'team-alpha',
        'is_personal' => true,
    ]);

    Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'url' => 'https://github.com',
        'url_hash' => hash('sha256', 'https://github.com'),
        'title' => 'GitHub',
    ]);

    $backupService = app(VaultBackupService::class);
    $backups = $backupService->backupTeam($team, 'local', $user);

    expect($backups)->toHaveCount(1);
    $backup = $backups[0];

    expect($backup->status)->toBe('completed')
        ->and($backup->provider)->toBe('local')
        ->and($backup->links_count)->toBe(1)
        ->and($backup->file_size)->toBeGreaterThan(0);

    $this->assertDatabaseHas('cloud_backups', [
        'id' => $backup->id,
        'team_id' => $team->id,
        'provider' => 'local',
        'status' => 'completed',
    ]);
});

test('it prunes old backups according to retention limit', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Team Retention',
        'slug' => 'team-retention',
        'is_personal' => true,
    ]);

    CloudStorageConfig::create([
        'team_id' => $team->id,
        'provider' => 'local',
        'is_active' => true,
        'retention_count' => 2,
    ]);

    $backupService = app(VaultBackupService::class);

    // Créer 4 sauvegardes consécutives
    $backupService->backupTeam($team, 'local', $user);
    $backupService->backupTeam($team, 'local', $user);
    $backupService->backupTeam($team, 'local', $user);
    $backupService->backupTeam($team, 'local', $user);

    $count = CloudBackup::where('team_id', $team->id)->where('provider', 'local')->count();
    expect($count)->toBe(2);
});

test('it can restore a vault from a backup zip archive', function () {
    $user = User::factory()->create();
    $sourceTeam = Team::create([
        'user_id' => $user->id,
        'name' => 'Source Team',
        'slug' => 'source-team',
        'is_personal' => true,
    ]);

    $targetUser = User::factory()->create();
    $targetTeam = Team::create([
        'user_id' => $targetUser->id,
        'name' => 'Target Team',
        'slug' => 'target-team',
        'is_personal' => false,
    ]);

    $folder = Folder::create([
        'team_id' => $sourceTeam->id,
        'user_id' => $user->id,
        'name' => 'Documentation',
        'slug' => 'documentation',
    ]);

    $link = Link::create([
        'user_id' => $user->id,
        'team_id' => $sourceTeam->id,
        'url' => 'https://php.net',
        'url_hash' => hash('sha256', 'https://php.net'),
        'title' => 'PHP Documentation',
        'folder_id' => $folder->id,
    ]);

    $backupService = app(VaultBackupService::class);
    $archive = $backupService->generateBackupArchive($sourceTeam, $user);

    $restoreService = app(VaultRestoreService::class);
    $result = $restoreService->restoreFromZip($archive['content'], $targetTeam, $targetUser);

    expect($result['imported_links'])->toBe(1)
        ->and($result['folders_created'])->toBe(1)
        ->and($result['skipped_links'])->toBe(0);

    $this->assertDatabaseHas('links', [
        'team_id' => $targetTeam->id,
        'url' => 'https://php.net',
        'title' => 'PHP Documentation',
    ]);

    $this->assertDatabaseHas('folders', [
        'team_id' => $targetTeam->id,
        'slug' => 'documentation',
    ]);
});

test('artisan vault:backup-cloud command runs successfully', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'CLI Team',
        'slug' => 'cli-team',
        'is_personal' => true,
    ]);

    $this->artisan('vault:backup-cloud', [
        '--team' => $team->id,
        '--provider' => 'local',
    ])->assertSuccessful();

    $this->assertDatabaseHas('cloud_backups', [
        'team_id' => $team->id,
        'provider' => 'local',
        'status' => 'completed',
    ]);
});
