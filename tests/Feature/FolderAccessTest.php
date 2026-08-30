<?php

use App\Enums\ContentType;
use App\Enums\FolderRole;
use App\Enums\FolderVisibility;
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
