<?php

use App\Actions\LinkActions\CreateLinkAction;
use App\Enums\ContentType;
use App\Enums\FolderRole;
use App\Enums\FolderVisibility;
use App\Enums\LinkVisibility;
use App\Models\Category;
use App\Models\Folder;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use LaravelDaily\FilaTeams\Models\Team;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Owner']);
    $this->member = User::factory()->create(['name' => 'Member']);
    $this->otherUser = User::factory()->create(['name' => 'Other']);

    $this->team = Team::create([
        'name' => 'Projets Team',
        'slug' => 'projets-team',
        'is_personal' => false,
    ]);

    $this->owner->teams()->attach($this->team->id, ['role' => 'owner']);
    $this->member->teams()->attach($this->team->id, ['role' => 'member']);

    $this->category = Category::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'name' => 'Design & Dev',
        'slug' => 'design-dev',
    ]);
});

test('a user can create private, team and restricted folders', function () {
    $privateFolder = Folder::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'category_id' => $this->category->id,
        'name' => 'Mes recherches privées',
        'visibility' => FolderVisibility::Private,
    ]);

    $teamFolder = Folder::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'category_id' => $this->category->id,
        'name' => 'Ressources Équipe',
        'visibility' => FolderVisibility::Team,
    ]);

    $restrictedFolder = Folder::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'category_id' => $this->category->id,
        'name' => 'Projet Secret Acme',
        'visibility' => FolderVisibility::Restricted,
    ]);
    $restrictedFolder->members()->attach($this->member->id, ['role' => FolderRole::Viewer->value]);

    expect($privateFolder->visibility)->toBe(FolderVisibility::Private)
        ->and($teamFolder->visibility)->toBe(FolderVisibility::Team)
        ->and($restrictedFolder->visibility)->toBe(FolderVisibility::Restricted)
        ->and($restrictedFolder->members)->toHaveCount(1);
});

test('an invited member only sees team and restricted folders assigned to them', function () {
    $privateFolder = Folder::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'name' => 'Dossier Secret Owner',
        'visibility' => FolderVisibility::Private,
    ]);

    $teamFolder = Folder::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'name' => 'Dossier Public Equipe',
        'visibility' => FolderVisibility::Team,
    ]);

    $restrictedFolderForMember = Folder::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'name' => 'Projet Partagé Avec Member',
        'visibility' => FolderVisibility::Restricted,
    ]);
    $restrictedFolderForMember->members()->attach($this->member->id, ['role' => FolderRole::Viewer->value]);

    $restrictedFolderForOther = Folder::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'name' => 'Projet Sans Member',
        'visibility' => FolderVisibility::Restricted,
    ]);
    $restrictedFolderForOther->members()->attach($this->otherUser->id, ['role' => FolderRole::Viewer->value]);

    // Test scope accessibleForUser for member
    $accessibleFolders = Folder::accessibleForUser($this->member)->pluck('id')->toArray();

    expect($accessibleFolders)
        ->toContain($teamFolder->id)
        ->toContain($restrictedFolderForMember->id)
        ->not->toContain($privateFolder->id)
        ->not->toContain($restrictedFolderForOther->id);
});

test('link visibility is inherited from the parent folder', function () {
    $privateFolder = Folder::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'name' => 'Secret',
        'visibility' => FolderVisibility::Private,
    ]);

    $sharedFolder = Folder::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'name' => 'Partagé',
        'visibility' => FolderVisibility::Restricted,
    ]);
    $sharedFolder->members()->attach($this->member->id, ['role' => FolderRole::Viewer->value]);

    // Link in private folder
    $privateLink = Link::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'folder_id' => $privateFolder->id,
        'title' => 'Lien Privé',
        'url' => 'https://private.example.com',
        'content_type' => ContentType::Other,
    ]);

    // Link in shared folder
    $sharedLink = Link::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'folder_id' => $sharedFolder->id,
        'title' => 'Lien Partagé',
        'url' => 'https://shared.example.com',
        'content_type' => ContentType::Other,
    ]);

    // Link created directly by member
    $memberLink = Link::create([
        'user_id' => $this->member->id,
        'team_id' => $this->team->id,
        'title' => 'Lien de Member',
        'url' => 'https://member.example.com',
        'content_type' => ContentType::Other,
    ]);

    $accessibleLinks = Link::accessibleForUser($this->member)->pluck('id')->toArray();

    expect($accessibleLinks)
        ->toContain($sharedLink->id)
        ->toContain($memberLink->id)
        ->not->toContain($privateLink->id);

    // Test Gate / Policy
    expect(Gate::forUser($this->member)->allows('view', $sharedLink))->toBeTrue()
        ->and(Gate::forUser($this->member)->allows('view', $privateLink))->toBeFalse();
});

test('folder editor role allows modifying links in the folder while viewer role forbids it', function () {
    $folderWithEditor = Folder::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'name' => 'Dossier Éditable',
        'visibility' => FolderVisibility::Restricted,
    ]);
    $folderWithEditor->members()->attach($this->member->id, ['role' => FolderRole::Editor->value]);

    $folderWithViewer = Folder::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'name' => 'Dossier Lecture Seule',
        'visibility' => FolderVisibility::Restricted,
    ]);
    $folderWithViewer->members()->attach($this->member->id, ['role' => FolderRole::Viewer->value]);

    $linkEditable = Link::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'folder_id' => $folderWithEditor->id,
        'title' => 'Lien 1',
        'url' => 'https://example.com/1',
        'content_type' => ContentType::Other,
    ]);

    $linkReadOnly = Link::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'folder_id' => $folderWithViewer->id,
        'title' => 'Lien 2',
        'url' => 'https://example.com/2',
        'content_type' => ContentType::Other,
    ]);

    expect(Gate::forUser($this->member)->allows('update', $linkEditable))->toBeTrue()
        ->and(Gate::forUser($this->member)->allows('update', $linkReadOnly))->toBeFalse();
});

test('multiple isolated links can be assigned to a folder in bulk', function () {
    $folder = Folder::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'category_id' => $this->category->id,
        'name' => 'Collection Projets',
        'visibility' => FolderVisibility::Team,
    ]);

    $link1 = Link::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'title' => 'Lien Isolé 1',
        'url' => 'https://isolated1.com',
        'content_type' => ContentType::Other,
    ]);

    $link2 = Link::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'title' => 'Lien Isolé 2',
        'url' => 'https://isolated2.com',
        'content_type' => ContentType::Other,
    ]);

    expect($link1->folder_id)->toBeNull()
        ->and($link2->folder_id)->toBeNull();

    // Simulate bulk update
    $links = collect([$link1, $link2]);
    foreach ($links as $link) {
        $link->update([
            'folder_id' => $folder->id,
            'category_id' => $folder->category_id,
        ]);
    }

    expect($link1->fresh()->folder_id)->toBe($folder->id)
        ->and($link1->fresh()->category_id)->toBe($this->category->id)
        ->and($link2->fresh()->folder_id)->toBe($folder->id);
});

test('link with private visibility is only visible to its creator', function () {
    $privateLink = Link::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'title' => 'Lien Privé Owner',
        'url' => 'https://private-owner.com',
        'content_type' => ContentType::Other,
        'visibility' => LinkVisibility::Private,
    ]);

    $accessibleByMember = Link::where('team_id', $this->team->id)
        ->accessibleForUser($this->member)
        ->pluck('id')
        ->toArray();

    expect($accessibleByMember)->not->toContain($privateLink->id);

    $accessibleByOwner = Link::where('team_id', $this->team->id)
        ->accessibleForUser($this->owner)
        ->pluck('id')
        ->toArray();

    expect($accessibleByOwner)->toContain($privateLink->id);
});

test('link with team visibility is visible to all team members', function () {
    $teamLink = Link::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'title' => 'Lien Équipe',
        'url' => 'https://team-link.com',
        'content_type' => ContentType::Other,
        'visibility' => LinkVisibility::Team,
    ]);

    // Le membre de l'équipe doit voir le lien team
    $accessibleByMember = Link::where('team_id', $this->team->id)
        ->accessibleForUser($this->member)
        ->pluck('id')
        ->toArray();

    expect($accessibleByMember)->toContain($teamLink->id);

    // Le créateur (owner) doit aussi voir son lien via la clause user_id
    $accessibleByOwner = Link::where('team_id', $this->team->id)
        ->accessibleForUser($this->owner)
        ->pluck('id')
        ->toArray();

    expect($accessibleByOwner)->toContain($teamLink->id);
});

test('link with restricted visibility is only visible to assigned members', function () {
    $restrictedLink = Link::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'title' => 'Lien Restreint',
        'url' => 'https://restricted-link.com',
        'content_type' => ContentType::Other,
        'visibility' => LinkVisibility::Restricted,
    ]);

    // Avant d'assigner le membre
    $accessibleByMember = Link::where('team_id', $this->team->id)
        ->accessibleForUser($this->member)
        ->pluck('id')
        ->toArray();

    expect($accessibleByMember)->not->toContain($restrictedLink->id);

    // Assigner le membre via la table pivot link_user
    $restrictedLink->members()->attach($this->member->id);

    // Après assignation
    $accessibleByMember = Link::where('team_id', $this->team->id)
        ->accessibleForUser($this->member)
        ->pluck('id')
        ->toArray();

    expect($accessibleByMember)->toContain($restrictedLink->id);

    // L'utilisateur non assigné ne voit pas le lien
    $accessibleByOther = Link::where('team_id', $this->team->id)
        ->accessibleForUser($this->otherUser)
        ->pluck('id')
        ->toArray();

    expect($accessibleByOther)->not->toContain($restrictedLink->id);
});

test('a user can view the custom folder detail page with links and stats', function () {
    $folder = Folder::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'category_id' => $this->category->id,
        'name' => 'Dossier Marketing',
        'visibility' => FolderVisibility::Team,
    ]);

    $link1 = Link::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'folder_id' => $folder->id,
        'title' => 'Campagne Google Ads',
        'url' => 'https://ads.google.com',
        'content_type' => ContentType::Other,
        'visit_count' => 12,
        'is_favorite' => true,
    ]);

    $link2 = Link::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'folder_id' => $folder->id,
        'title' => 'Analytics Dashboard',
        'url' => 'https://analytics.google.com',
        'content_type' => ContentType::Other,
        'visit_count' => 8,
    ]);

    $response = $this->actingAs($this->owner)
        ->get("/app/{$this->team->slug}/folders/{$folder->id}");

    $response->assertOk();
    $response->assertSee('Dossier Marketing');
    $response->assertSee('Campagne Google Ads');
    $response->assertSee('Analytics Dashboard');
    $response->assertSee('Total des liens');
    $response->assertSee('Total des visites');
});

test('create link action handles string and array metadata without throwing TypeError', function () {
    $folder = Folder::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'name' => 'Dossier Dev',
        'visibility' => FolderVisibility::Team,
    ]);

    // Test avec metadata sous forme de string JSON (comme envoyé par le formulaire Filament)
    $link = CreateLinkAction::execute([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'folder_id' => $folder->id,
        'url' => 'https://laravel.com/docs',
        'title' => 'Laravel Docs',
        'metadata' => '{"custom_key": "custom_value"}',
        'tags' => ['php', 'laravel'],
    ]);

    expect($link)->toBeInstanceOf(Link::class)
        ->and($link->folder_id)->toBe($folder->id)
        ->and($link->tags)->toBe('php,laravel')
        ->and($link->metadata)->toBeArray();
});

test('folder visibility change permissions strictly respect creator space and editor role', function () {
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
        'name' => 'Dossier Restreint Team 1',
        'slug' => 'dossier-restreint-team-1',
        'visibility' => FolderVisibility::Restricted,
    ]);

    // Guest Editor has editor role on folder
    $restrictedFolder->members()->attach($guestEditor->id, ['role' => FolderRole::Editor->value]);
    // Guest Viewer has viewer role on folder
    $restrictedFolder->members()->attach($guestViewer->id, ['role' => FolderRole::Viewer->value]);

    // 1. Creator on origin team -> ALLOWED
    expect($restrictedFolder->canChangeVisibility($creator, $team1))->toBeTrue();

    // 2. Guest with editor role on origin team -> ALLOWED
    expect($restrictedFolder->canChangeVisibility($guestEditor, $team1))->toBeTrue();

    // 3. Guest with viewer role on origin team -> DENIED
    expect($restrictedFolder->canChangeVisibility($guestViewer, $team1))->toBeFalse();

    // 4. In a different team (team 2) -> DENIED even if creator
    expect($restrictedFolder->canChangeVisibility($creator, $team2))->toBeFalse();

    // 5. Unrelated user -> DENIED
    expect($restrictedFolder->canChangeVisibility($otherUser, $team1))->toBeFalse();
});

test('view folder page shows change visibility button for authorized user', function () {
    $folder = Folder::create([
        'user_id' => $this->owner->id,
        'team_id' => $this->team->id,
        'name' => 'Dossier Marketing Visibilité',
        'slug' => 'dossier-marketing-visibilite',
        'visibility' => FolderVisibility::Team,
    ]);

    $response = $this->actingAs($this->owner)
        ->get("/app/{$this->team->slug}/folders/{$folder->id}");

    $response->assertOk();
    $response->assertSee('Visibilité');
});
