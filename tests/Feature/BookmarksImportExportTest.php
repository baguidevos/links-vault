<?php

declare(strict_types=1);

use App\Models\Folder;
use App\Models\Link;
use App\Models\Tag;
use App\Models\User;
use App\Services\BookmarksExportService;
use App\Services\BookmarksImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelDaily\FilaTeams\Models\Team;

uses(RefreshDatabase::class);

test('BookmarksImportService imports Netscape HTML with nested folders and tags', function () {
    $user = User::factory()->create();
    $team = Team::create(['name' => 'Import Team', 'slug' => 'import-team', 'is_personal' => true]);
    $user->teams()->attach($team->id, ['role' => 'owner']);

    $html = <<<'HTML'
<!DOCTYPE NETSCAPE-Bookmark-file-1>
<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
<TITLE>Bookmarks</TITLE>
<H1>Bookmarks</H1>
<DL><p>
    <DT><A HREF="https://laravel.com" ADD_DATE="1700000000" TAGS="php,framework">Laravel</A>
    <DT><H3 ADD_DATE="1700000000">Développement Web</H3>
    <DL><p>
        <DT><A HREF="https://vuejs.org" ADD_DATE="1700000001" TAGS="javascript,frontend">Vue.js</A>
        <DT><A HREF="https://tailwindcss.com" ADD_DATE="1700000002" TAGS="css,design">Tailwind CSS</A>
    </DL><p>
</DL><p>
HTML;

    /** @var BookmarksImportService $importer */
    $importer = app(BookmarksImportService::class);
    $result = $importer->importFromNetscapeHtml($html, $user, $team->id);

    expect($result['imported_count'])->toBe(3)
        ->and($result['folders_created'])->toBe(1)
        ->and($result['skipped_count'])->toBe(0);

    // Vérifier les liens créés
    $laravelLink = Link::where('team_id', $team->id)->where('url', 'https://laravel.com')->first();
    expect($laravelLink)->not->toBeNull()
        ->and($laravelLink->title)->toBe('Laravel')
        ->and($laravelLink->folder_id)->toBeNull()
        ->and($laravelLink->tags()->pluck('name')->all())->toContain('php', 'framework');

    $vueLink = Link::where('team_id', $team->id)->where('url', 'https://vuejs.org')->first();
    expect($vueLink)->not->toBeNull()
        ->and($vueLink->folder)->not->toBeNull()
        ->and($vueLink->folder->name)->toBe('Développement Web')
        ->and($vueLink->tags()->pluck('name')->all())->toContain('javascript', 'frontend');

    // Test de ré-importation pour vérifier que les doublons sont ignorés
    $secondResult = $importer->importFromNetscapeHtml($html, $user, $team->id);
    expect($secondResult['imported_count'])->toBe(0)
        ->and($secondResult['skipped_count'])->toBe(3);
});

test('BookmarksImportService imports JSON bookmarks correctly', function () {
    $user = User::factory()->create();
    $team = Team::create(['name' => 'JSON Team', 'slug' => 'json-team', 'is_personal' => true]);
    $user->teams()->attach($team->id, ['role' => 'owner']);

    $json = json_encode([
        'bookmarks' => [
            [
                'url' => 'https://github.com',
                'title' => 'GitHub',
                'description' => 'Plateforme de code',
                'folder' => 'Outils Dev',
                'tags' => ['git', 'code'],
            ],
            [
                'url' => 'https://pestphp.com',
                'title' => 'Pest PHP',
                'description' => 'Framework de test élégant',
                'folder' => 'Outils Dev',
                'tags' => ['testing', 'php'],
            ],
        ],
    ]);

    /** @var BookmarksImportService $importer */
    $importer = app(BookmarksImportService::class);
    $result = $importer->importFromJson($json, $user, $team->id);

    expect($result['imported_count'])->toBe(2)
        ->and($result['folders_created'])->toBe(1);

    $githubLink = Link::where('team_id', $team->id)->where('url', 'https://github.com')->first();
    expect($githubLink)->not->toBeNull()
        ->and($githubLink->title)->toBe('GitHub')
        ->and($githubLink->folder->name)->toBe('Outils Dev')
        ->and($githubLink->tags()->pluck('name')->all())->toContain('git', 'code');
});

test('BookmarksImportService imports CSV bookmarks correctly', function () {
    $user = User::factory()->create();
    $team = Team::create(['name' => 'CSV Team', 'slug' => 'csv-team', 'is_personal' => true]);
    $user->teams()->attach($team->id, ['role' => 'owner']);

    $csv = "url,title,folder,tags,description\nhttps://alpinejs.dev,Alpine.js,Frontend,javascript,Léger et réactif\nhttps://livewire.laravel.com,Livewire,Frontend,laravel,Fullstack Blade";

    /** @var BookmarksImportService $importer */
    $importer = app(BookmarksImportService::class);
    $result = $importer->importFromCsv($csv, $user, $team->id);

    expect($result['imported_count'])->toBe(2)
        ->and($result['folders_created'])->toBe(1);

    $alpineLink = Link::where('team_id', $team->id)->where('url', 'https://alpinejs.dev')->first();
    expect($alpineLink)->not->toBeNull()
        ->and($alpineLink->title)->toBe('Alpine.js')
        ->and($alpineLink->description)->toBe('Léger et réactif');
});

test('BookmarksExportService generates valid Netscape HTML and JSON exports', function () {
    $user = User::factory()->create();
    $team = Team::create(['name' => 'Export Team', 'slug' => 'export-team', 'is_personal' => true]);
    $user->teams()->attach($team->id, ['role' => 'owner']);

    $folder = Folder::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'name' => 'Articles IA',
        'slug' => 'articles-ia',
    ]);

    $link = Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'folder_id' => $folder->id,
        'url' => 'https://openai.com',
        'url_hash' => hash('sha256', 'https://openai.com'),
        'title' => 'OpenAI Official',
    ]);

    $tag = Tag::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'name' => 'ai',
        'slug' => 'ai',
    ]);
    $link->tags()->attach($tag->id);

    /** @var BookmarksExportService $exporter */
    $exporter = app(BookmarksExportService::class);

    $htmlExport = $exporter->exportToHtml($user, $team->id);
    expect($htmlExport)->toContain('<!DOCTYPE NETSCAPE-Bookmark-file-1>')
        ->and($htmlExport)->toContain('Articles IA')
        ->and($htmlExport)->toContain('https://openai.com')
        ->and($htmlExport)->toContain('OpenAI Official')
        ->and($htmlExport)->toContain('TAGS="ai"');

    $jsonExport = $exporter->exportToJson($user, $team->id);
    expect($jsonExport)->toContain('"url": "https://openai.com"')
        ->and($jsonExport)->toContain('"folder": "Articles IA"')
        ->and($jsonExport)->toContain('"ai"');

    $csvExport = $exporter->exportToCsv($user, $team->id);
    expect($csvExport)->toContain('https://openai.com')
        ->and($csvExport)->toContain('Articles IA');
});
