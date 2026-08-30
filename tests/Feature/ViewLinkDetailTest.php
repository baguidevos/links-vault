<?php

use App\Enums\ContentType;
use App\Enums\FolderRole;
use App\Enums\FolderVisibility;
use App\Enums\LinkVisibility;
use App\Models\Category;
use App\Models\Folder;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelDaily\FilaTeams\Models\Team;

uses(RefreshDatabase::class);

test('a user can view the link detail page with folder and metadata', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'name' => 'Design Team',
        'slug' => 'design-team',
        'is_personal' => true,
    ]);
    $user->teams()->attach($team->id, ['role' => 'owner']);

    $category = Category::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'name' => 'Apprentissage',
        'slug' => 'apprentissage',
    ]);

    $folder = Folder::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'category_id' => $category->id,
        'name' => 'Dossier React',
        'slug' => 'dossier-react',
        'visibility' => FolderVisibility::Team,
    ]);

    $link = Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'category_id' => $category->id,
        'folder_id' => $folder->id,
        'title' => 'Tutorial Complete de React pour Débutants',
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'description' => 'Un tutoriel complet pour maîtriser React from scratch.',
        'content_type' => ContentType::Youtube,
        'objective' => 'Compléter les sections 1-6 avant vendredi',
        'tags' => 'React,JavaScript,Tutorial',
        'ai_summary' => 'Introduction aux fondamentaux de React et des hooks.',
        'visibility' => LinkVisibility::Team,
        'is_favorite' => false,
        'visit_count' => 5,
    ]);

    $response = $this->actingAs($user)
        ->get("/app/{$team->slug}/links/{$link->id}");

    $response->assertOk();
    $response->assertSee('Tutorial Complete de React pour Débutants');
    $response->assertSee('Dossier React');
    $response->assertSee('Apprentissage');
    $response->assertSee('Compléter les sections 1-6 avant vendredi');
    $response->assertSee('https://www.youtube.com/embed/dQw4w9WgXcQ');
    $response->assertSee('Résumé IA');
    $response->assertSee('Modifier la visibilité');
});

test('link visibility change permissions strictly respect creator space and editor role', function () {
    $creator = User::factory()->create();
    $guestEditor = User::factory()->create();
    $guestViewer = User::factory()->create();
    $otherUser = User::factory()->create();

    $team1 = Team::create(['name' => 'Team 1', 'slug' => 'team-1', 'is_personal' => true]);
    $team2 = Team::create(['name' => 'Team 2', 'slug' => 'team-2', 'is_personal' => false]);

    $creator->teams()->attach($team1->id, ['role' => 'owner']);
    $guestEditor->teams()->attach($team1->id, ['role' => 'member']);
    $guestViewer->teams()->attach($team1->id, ['role' => 'member']);
    $otherUser->teams()->attach($team2->id, ['role' => 'owner']);

    $restrictedFolder = Folder::create([
        'user_id' => $creator->id,
        'team_id' => $team1->id,
        'name' => 'Dossier Restreint',
        'slug' => 'dossier-restreint',
        'visibility' => FolderVisibility::Restricted,
    ]);

    // Guest Editor has editor role on folder
    $restrictedFolder->members()->attach($guestEditor->id, ['role' => FolderRole::Editor->value]);
    // Guest Viewer has viewer role on folder
    $restrictedFolder->members()->attach($guestViewer->id, ['role' => FolderRole::Viewer->value]);

    $link = Link::create([
        'user_id' => $creator->id,
        'team_id' => $team1->id,
        'folder_id' => $restrictedFolder->id,
        'title' => 'Doc Interne',
        'url' => 'https://example.com/doc',
        'visibility' => LinkVisibility::Restricted,
    ]);

    // 1. Creator on link's origin team -> ALLOWED
    expect($link->canChangeVisibility($creator, $team1))->toBeTrue();

    // 2. Guest with editor role on folder in origin team -> ALLOWED
    expect($link->canChangeVisibility($guestEditor, $team1))->toBeTrue();

    // 3. Guest with viewer role on folder in origin team -> DENIED
    expect($link->canChangeVisibility($guestViewer, $team1))->toBeFalse();

    // 4. In a different team (team 2) -> DENIED even if creator
    expect($link->canChangeVisibility($creator, $team2))->toBeFalse();

    // 5. Unrelated user -> DENIED
    expect($link->canChangeVisibility($otherUser, $team1))->toBeFalse();
});
