<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Folder;
use App\Models\Link;
use App\Models\SyncSetting;
use App\Models\SyncTombstone;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use App\Services\Sync\DesktopSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('it rejects push from unauthenticated users', function () {
    $response = $this->postJson(route('api.sync.push'), [
        'entities' => [],
    ]);

    $response->assertUnauthorized();
});

test('it rejects sync on teams the user does not belong to', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherTeam = Team::create(['name' => 'Foreign Team', 'slug' => 'foreign-team', 'is_personal' => true]);
    $otherUser->teams()->attach($otherTeam->id, ['role' => 'owner']);

    $response = $this->actingAs($user, 'sanctum')->postJson(route('api.sync.push'), [
        'team_uuid' => $otherTeam->uuid,
        'entities' => ['links' => []],
    ]);

    $response->assertForbidden();
});

test('it successfully pushes folders, categories, tags, and links to Web', function () {
    $user = User::factory()->create();
    $team = Team::create(['name' => 'My Team', 'slug' => 'my-team', 'is_personal' => true]);
    $user->teams()->attach($team->id, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $folderUuid = (string) Str::uuid();
    $categoryUuid = (string) Str::uuid();
    $linkUuid = (string) Str::uuid();
    $tagUuid = (string) Str::uuid();

    $payload = [
        'team_uuid' => $team->uuid,
        'entities' => [
            'categories' => [
                [
                    'uuid' => $categoryUuid,
                    'name' => 'Développement',
                    'slug' => 'developpement',
                    'color' => '#3b82f6',
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
            'folders' => [
                [
                    'uuid' => $folderUuid,
                    'name' => 'Frameworks PHP',
                    'slug' => 'frameworks-php',
                    'color' => '#ef4444',
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
            'tags' => [
                [
                    'uuid' => $tagUuid,
                    'name' => 'laravel',
                    'slug' => 'laravel',
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
            'links' => [
                [
                    'uuid' => $linkUuid,
                    'url' => 'https://laravel.com/docs',
                    'title' => 'Laravel Documentation',
                    'description' => 'Documentation officielle Laravel',
                    'folder_uuid' => $folderUuid,
                    'category_uuid' => $categoryUuid,
                    'tags' => ['laravel'],
                    'is_favorite' => true,
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
            'deleted' => [],
        ],
    ];

    $response = $this->actingAs($user, 'sanctum')->postJson(route('api.sync.push'), $payload);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('processed.folders', 1)
        ->assertJsonPath('processed.categories', 1)
        ->assertJsonPath('processed.tags', 1)
        ->assertJsonPath('processed.links', 1);

    expect(Folder::where('uuid', $folderUuid)->where('team_id', $team->id)->exists())->toBeTrue()
        ->and(Category::where('uuid', $categoryUuid)->where('team_id', $team->id)->exists())->toBeTrue()
        ->and(Tag::where('name', 'laravel')->where('team_id', $team->id)->exists())->toBeTrue();

    $createdLink = Link::where('uuid', $linkUuid)->first();
    expect($createdLink)->not->toBeNull()
        ->and($createdLink->url)->toBe('https://laravel.com/docs')
        ->and($createdLink->folder_id)->toBe(Folder::where('uuid', $folderUuid)->value('id'))
        ->and($createdLink->category_id)->toBe(Category::where('uuid', $categoryUuid)->value('id'))
        ->and($createdLink->tags()->pluck('name')->all())->toContain('laravel');
});

test('it handles conflict resolution with Last-Write-Wins (LWW)', function () {
    $user = User::factory()->create();
    $team = Team::create(['name' => 'LWW Team', 'slug' => 'lww-team', 'is_personal' => true]);
    $user->teams()->attach($team->id, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $linkUuid = (string) Str::uuid();

    // 1. Créer un lien existant sur le serveur daté de 12:00
    $link = Link::create([
        'uuid' => $linkUuid,
        'user_id' => $user->id,
        'team_id' => $team->id,
        'url' => 'https://example.com',
        'url_hash' => hash('sha256', 'https://example.com'),
        'title' => 'Titre Serveur Récent',
    ]);
    $link->updated_at = now();
    $link->save();

    // 2. Tenter un push avec une date antérieure (10:00) -> doit être ignoré
    $stalePayload = [
        'team_uuid' => $team->uuid,
        'entities' => [
            'links' => [
                [
                    'uuid' => $linkUuid,
                    'url' => 'https://example.com',
                    'title' => 'Titre Obsolète Local',
                    'updated_at' => now()->subHours(2)->toIso8601String(),
                ],
            ],
        ],
    ];

    $response = $this->actingAs($user, 'sanctum')->postJson(route('api.sync.push'), $stalePayload);
    $response->assertOk()->assertJsonPath('processed.links', 0);

    expect($link->fresh()->title)->toBe('Titre Serveur Récent');

    // 3. Tenter un push avec une date future -> doit être accepté
    $freshPayload = [
        'team_uuid' => $team->uuid,
        'entities' => [
            'links' => [
                [
                    'uuid' => $linkUuid,
                    'url' => 'https://example.com',
                    'title' => 'Titre Frais Local',
                    'updated_at' => now()->addMinutes(5)->toIso8601String(),
                ],
            ],
        ],
    ];

    $responseFresh = $this->actingAs($user, 'sanctum')->postJson(route('api.sync.push'), $freshPayload);
    $responseFresh->assertOk()->assertJsonPath('processed.links', 1);

    expect($link->fresh()->title)->toBe('Titre Frais Local');
});

test('it pulls changed entities and tombstones since specified date', function () {
    $user = User::factory()->create();
    $team = Team::create(['name' => 'Pull Team', 'slug' => 'pull-team', 'is_personal' => true]);
    $user->teams()->attach($team->id, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $link = Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'url' => 'https://pestphp.com',
        'url_hash' => hash('sha256', 'https://pestphp.com'),
        'title' => 'Pest PHP',
    ]);

    // Simuler une suppression qui enregistre un tombstone
    $deletedUuid = (string) Str::uuid();
    SyncTombstone::create([
        'team_id' => $team->id,
        'entity_type' => 'Link',
        'entity_uuid' => $deletedUuid,
        'deleted_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson(route('api.sync.pull', [
        'team_uuid' => $team->uuid,
        'since' => now()->subHour()->toIso8601String(),
    ]));

    $response->assertOk()
        ->assertJsonStructure([
            'server_time',
            'entities' => ['folders', 'categories', 'tags', 'links', 'deleted'],
        ]);

    $data = $response->json();
    expect($data['entities']['links'])->toHaveCount(1)
        ->and($data['entities']['links'][0]['url'])->toBe('https://pestphp.com')
        ->and($data['entities']['deleted'])->toHaveCount(1)
        ->and($data['entities']['deleted'][0]['uuid'])->toBe($deletedUuid);
});

test('it records tombstone when Syncable model is deleted', function () {
    $user = User::factory()->create();
    $team = Team::create(['name' => 'Tombstone Team', 'slug' => 'tombstone-team', 'is_personal' => true]);

    $link = Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'url' => 'https://todelete.com',
        'url_hash' => hash('sha256', 'https://todelete.com'),
        'title' => 'To Delete',
    ]);

    $linkUuid = $link->uuid;
    expect($linkUuid)->not->toBeNull();

    $link->delete();

    $tombstone = SyncTombstone::where('entity_uuid', $linkUuid)->first();
    expect($tombstone)->not->toBeNull()
        ->and($tombstone->entity_type)->toBe('Link')
        ->and($tombstone->team_id)->toBe($team->id);
});

test('DesktopSyncService coordinates push and pull via HTTP client', function () {
    $user = User::factory()->create();
    $team = Team::create(['name' => 'Client Team', 'slug' => 'client-team', 'is_personal' => true]);
    $user->teams()->attach($team->id, ['role' => 'owner']);

    SyncSetting::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'server_url' => 'https://web.linksvault.test',
        'api_token' => 'test-fake-sanctum-token',
        'auto_sync_enabled' => true,
    ]);

    $remoteLinkUuid = (string) Str::uuid();

    Http::fake([
        'https://web.linksvault.test/api/v1/sync/push' => Http::response([
            'success' => true,
            'processed' => ['links' => 0],
            'server_time' => now()->toIso8601String(),
        ], 200),
        'https://web.linksvault.test/api/v1/sync/pull*' => Http::response([
            'server_time' => now()->toIso8601String(),
            'entities' => [
                'folders' => [],
                'categories' => [],
                'tags' => [],
                'links' => [
                    [
                        'uuid' => $remoteLinkUuid,
                        'url' => 'https://github.com/laravel/framework',
                        'title' => 'Laravel Framework',
                        'description' => 'Source code',
                        'folder_uuid' => null,
                        'category_uuid' => null,
                        'tags' => ['github'],
                        'updated_at' => now()->toIso8601String(),
                    ],
                ],
                'deleted' => [],
            ],
        ], 200),
    ]);

    /** @var DesktopSyncService $service */
    $service = app(DesktopSyncService::class);
    $result = $service->sync($user, $team);

    expect($result['success'])->toBeTrue();

    // Le lien distant a été importé dans la base locale
    $importedLink = Link::where('uuid', $remoteLinkUuid)->first();
    expect($importedLink)->not->toBeNull()
        ->and($importedLink->url)->toBe('https://github.com/laravel/framework')
        ->and($importedLink->title)->toBe('Laravel Framework');

    $settings = SyncSetting::where('user_id', $user->id)->first();
    expect($settings->sync_status)->toBe('idle')
        ->and($settings->last_synced_at)->not->toBeNull();
});
