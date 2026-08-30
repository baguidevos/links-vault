<?php

use App\Enums\FolderVisibility;
use App\Models\Category;
use App\Models\Folder;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelDaily\FilaTeams\Models\Team;

uses(RefreshDatabase::class);

test('it can authenticate and receive an API token', function () {
    $user = User::factory()->create([
        'email' => 'test@linksvault.app',
        'password' => 'secret123',
    ]);

    $response = $this->postJson(route('api.auth.token'), [
        'email' => 'test@linksvault.app',
        'password' => 'secret123',
        'device_name' => 'Chrome Extension Test',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'token',
            'user' => ['id', 'name', 'email'],
            'message',
        ]);
});

test('it returns folders filtered by the selected team and accessible to the user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $team1 = Team::create([
        'name' => 'Espace 1',
        'slug' => 'espace-1',
        'user_id' => $user->id,
        'is_personal' => true,
    ]);
    $team2 = Team::create([
        'name' => 'Espace 2',
        'slug' => 'espace-2',
        'user_id' => $user->id,
        'is_personal' => false,
    ]);

    $user->teams()->attach($team1->id, ['role' => 'owner']);
    $user->teams()->attach($team2->id, ['role' => 'owner']);
    $otherUser->teams()->attach($team1->id, ['role' => 'member']);
    $user->update(['current_team_id' => $team1->id]);

    // Folder in team 1
    $folderTeam1 = Folder::create([
        'user_id' => $user->id,
        'team_id' => $team1->id,
        'name' => 'Dossier Espace 1',
        'slug' => 'dossier-espace-1',
        'visibility' => FolderVisibility::Team,
    ]);

    // Folder in team 2
    $folderTeam2 = Folder::create([
        'user_id' => $user->id,
        'team_id' => $team2->id,
        'name' => 'Dossier Espace 2',
        'slug' => 'dossier-espace-2',
        'visibility' => FolderVisibility::Team,
    ]);

    // 1. Request context without team_id -> defaults to team 1
    $responseDefault = $this->actingAs($user, 'sanctum')->getJson(route('api.context'));
    $responseDefault->assertOk()
        ->assertJsonPath('current_team_id', $team1->id)
        ->assertJsonCount(1, 'folders')
        ->assertJsonPath('folders.0.name', 'Dossier Espace 1');

    // 2. Request context specifically for team 2
    $responseTeam2 = $this->actingAs($user, 'sanctum')->getJson(route('api.context', ['team_id' => $team2->id]));
    $responseTeam2->assertOk()
        ->assertJsonPath('current_team_id', $team2->id)
        ->assertJsonCount(1, 'folders')
        ->assertJsonPath('folders.0.name', 'Dossier Espace 2');
});

test('it can store a link from the API with folder', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'name' => 'Mon Espace',
        'slug' => 'mon-espace',
        'user_id' => $user->id,
        'is_personal' => true,
    ]);
    $user->teams()->attach($team->id, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $category = Category::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'name' => 'Tutorials',
        'slug' => 'tutorials',
    ]);

    $folder = Folder::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'category_id' => $category->id,
        'name' => 'Documentation PHP',
        'slug' => 'documentation-php',
        'visibility' => FolderVisibility::Team,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson(route('api.links.store'), [
        'url' => 'https://laravel.com/docs',
        'title' => 'Laravel Documentation',
        'description' => 'Official docs for Laravel Framework',
        'folder_id' => $folder->id,
        'tags' => ['php', 'framework'],
        'is_favorite' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('link.title', 'Laravel Documentation')
        ->assertJsonPath('link.folder_id', $folder->id)
        ->assertJsonPath('link.category_id', $category->id)
        ->assertJsonPath('link.folder.name', 'Documentation PHP')
        ->assertJsonPath('link.is_favorite', true);

    $savedLink = Link::where('url', 'https://laravel.com/docs')->first();
    expect($savedLink)->not->toBeNull()
        ->and($savedLink->folder_id)->toBe($folder->id)
        ->and($savedLink->category_id)->toBe($category->id);
});
