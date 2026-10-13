<?php

use App\Console\Commands\ExtensionServerCommand;
use App\Enums\FolderVisibility;
use App\Models\Category;
use App\Models\Folder;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use LaravelDaily\FilaTeams\Models\Team;

uses(RefreshDatabase::class);

test('extension server saves link with folder_id, category_id, and tags', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'name' => 'Espace Test',
        'slug' => 'espace-test',
        'user_id' => $user->id,
        'is_personal' => true,
    ]);
    $user->teams()->attach($team->id, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $category = Category::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'name' => 'Développement',
        'slug' => 'developpement',
    ]);

    $folder = Folder::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'category_id' => $category->id,
        'name' => 'Dossier PHP',
        'slug' => 'dossier-php',
        'visibility' => FolderVisibility::Team,
    ]);

    $client = fopen('php://temp', 'r+');
    $command = new ExtensionServerCommand;
    $method = new ReflectionMethod($command, 'handleSaveRequest');
    $method->setAccessible(true);

    $payload = json_encode([
        'url' => 'https://pestphp.com/docs',
        'title' => 'Pest PHP Docs',
        'description' => 'Documentation de Pest PHP',
        'folder_id' => $folder->id,
        'category_id' => $category->id,
        'tags' => ['pest', 'testing'],
        'is_favorite' => true,
    ]);

    $method->invoke($command, $client, $payload);

    rewind($client);
    $response = stream_get_contents($client);
    fclose($client);

    expect($response)->toContain('HTTP/1.1 200 OK')
        ->and($response)->toContain('"status":"saved"');

    $link = Link::where('url', 'https://pestphp.com/docs')->first();
    expect($link)->not->toBeNull()
        ->and($link->folder_id)->toBe($folder->id)
        ->and($link->category_id)->toBe($category->id)
        ->and($link->title)->toBe('Pest PHP Docs')
        ->and($link->is_favorite)->toBeTrue()
        ->and($link->tags()->pluck('name')->all())->toContain('pest', 'testing');
});

test('extension server updates folder on existing link if saved again', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'name' => 'Espace Test',
        'slug' => 'espace-test',
        'user_id' => $user->id,
        'is_personal' => true,
    ]);
    $user->teams()->attach($team->id, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $folder1 = Folder::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'name' => 'Premier Dossier',
        'slug' => 'premier-dossier',
        'visibility' => FolderVisibility::Team,
    ]);

    $folder2 = Folder::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'name' => 'Deuxième Dossier',
        'slug' => 'deuxieme-dossier',
        'visibility' => FolderVisibility::Team,
    ]);

    // Initial link without folder or in folder1
    $link = Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'url' => 'https://github.com/laravel/framework',
        'url_hash' => hash('sha256', 'https://github.com/laravel/framework'),
        'title' => 'Laravel Framework',
        'folder_id' => $folder1->id,
    ]);

    $client = fopen('php://temp', 'r+');
    $command = new ExtensionServerCommand;
    $method = new ReflectionMethod($command, 'handleSaveRequest');
    $method->setAccessible(true);

    // Save again, but choosing folder2 by uuid
    $payload = json_encode([
        'url' => 'https://github.com/laravel/framework',
        'folder_uuid' => $folder2->uuid,
        'tags' => ['github', 'laravel'],
    ]);

    $method->invoke($command, $client, $payload);

    rewind($client);
    $response = stream_get_contents($client);
    fclose($client);

    expect($response)->toContain('HTTP/1.1 200 OK')
        ->and($response)->toContain('"is_duplicate":true');

    $link->refresh();
    expect($link->folder_id)->toBe($folder2->id)
        ->and($link->tags()->pluck('name')->all())->toContain('github', 'laravel');
});

test('extension server returns context with categories, folders, and tags', function () {
    $user = User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $team = Team::create([
        'name' => 'Mon Espace',
        'slug' => 'mon-espace',
        'user_id' => $user->id,
        'is_personal' => true,
    ]);
    $user->teams()->attach($team->id, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $folder = Folder::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'name' => 'Mes Favoris',
        'slug' => 'mes-favoris',
        'visibility' => FolderVisibility::Team,
    ]);

    $client = fopen('php://temp', 'r+');
    $command = new ExtensionServerCommand;
    $method = new ReflectionMethod($command, 'handleContextRequest');
    $method->setAccessible(true);

    $method->invoke($command, $client, $team->id);

    rewind($client);
    $response = stream_get_contents($client);
    fclose($client);

    expect($response)->toContain('HTTP/1.1 200 OK')
        ->and($response)->toContain('"name":"Mes Favoris"')
        ->and($response)->toContain('"uuid":"'.$folder->uuid.'"');
});

test('extension server saves link with legacy drive content_type normalized to google_drive', function () {
    Http::fake();

    $user = User::factory()->create();
    $team = Team::create([
        'name' => 'Espace Test',
        'slug' => 'espace-test',
        'user_id' => $user->id,
        'is_personal' => true,
    ]);
    $user->teams()->attach($team->id, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $client = fopen('php://temp', 'r+');
    $command = new ExtensionServerCommand;
    $method = new ReflectionMethod($command, 'handleSaveRequest');
    $method->setAccessible(true);

    $payload = json_encode([
        'url' => 'https://docs.google.com/document/d/1bHh8KBL0abc/edit',
        'title' => 'Partenaires FinAccess',
        'content_type' => 'drive',
        'favicon_url' => 'https://ssl.gstatic.com/docs/documents/images/kix-favicon7.ico',
    ]);

    $method->invoke($command, $client, $payload);

    rewind($client);
    $response = stream_get_contents($client);
    fclose($client);

    expect($response)->toContain('HTTP/1.1 200 OK')
        ->and($response)->toContain('"status":"saved"');

    $link = Link::where('url_hash', hash('sha256', 'https://docs.google.com/document/d/1bHh8KBL0abc/edit'))->first();
    expect($link)->not->toBeNull()
        ->and($link->content_type->value)->toBe('google_drive');
});

test('extension server saves google doc link correctly', function () {
    Http::fake();

    $user = User::factory()->create();
    $team = Team::create([
        'name' => 'Espace Test',
        'slug' => 'espace-test',
        'user_id' => $user->id,
        'is_personal' => true,
    ]);
    $user->teams()->attach($team->id, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $client = fopen('php://temp', 'r+');
    $command = new ExtensionServerCommand;
    $method = new ReflectionMethod($command, 'handleSaveRequest');
    $method->setAccessible(true);

    $payload = json_encode([
        'url' => 'https://docs.google.com/document/d/xyz123/edit',
        'title' => 'Doc Test',
        'content_type' => 'google_doc',
        'favicon_url' => 'https://ssl.gstatic.com/docs/documents/images/kix-favicon7.ico',
    ]);

    $method->invoke($command, $client, $payload);

    rewind($client);
    $response = stream_get_contents($client);
    fclose($client);

    expect($response)->toContain('HTTP/1.1 200 OK')
        ->and($response)->toContain('"status":"saved"');

    $link = Link::where('url_hash', hash('sha256', 'https://docs.google.com/document/d/xyz123/edit'))->first();
    expect($link)->not->toBeNull()
        ->and($link->content_type->value)->toBe('google_doc');
});

test('extension server handles open request gracefully', function () {
    Http::fake([
        '*' => Http::response(['status' => 'ok'], 200),
    ]);

    $client = fopen('php://temp', 'r+');
    $command = new ExtensionServerCommand;
    $method = new ReflectionMethod($command, 'handleOpenRequest');
    $method->setAccessible(true);

    $method->invoke($command, $client);

    rewind($client);
    $response = stream_get_contents($client);
    fclose($client);

    expect($response)->toContain('HTTP/1.1 200 OK')
        ->and($response)->toContain('"status":"ok"');
});
