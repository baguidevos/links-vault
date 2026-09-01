<?php

declare(strict_types=1);

use App\Enums\LinkHealthStatus;
use App\Jobs\CheckLinkHealthJob;
use App\Models\Link;
use App\Models\Team;
use App\Models\User;
use App\Services\LinkHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('it can detect a healthy link returning 200 OK', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://laravel.com*' => Http::response('Laravel Home', 200),
    ]);

    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Test Team',
        'slug' => 'test-team',
        'is_personal' => true,
    ]);

    $link = Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'url' => 'https://laravel.com',
        'title' => 'Laravel',
    ]);

    $service = app(LinkHealthService::class);
    $result = $service->checkLink($link);

    expect($result['status_code'])->toBe(200)
        ->and($result['health_status'])->toBe(LinkHealthStatus::Healthy)
        ->and($result['error'])->toBeNull();

    $link->refresh();
    expect($link->http_status)->toBe(200)
        ->and($link->health_status)->toBe(LinkHealthStatus::Healthy)
        ->and($link->isHealthy())->toBeTrue()
        ->and($link->isBroken())->toBeFalse()
        ->and($link->last_health_checked_at)->not->toBeNull();
});

test('it can detect a broken link returning 404 Not Found', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://example.com/missing*' => Http::response('Not Found', 404),
    ]);

    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Broken Team',
        'slug' => 'broken-team',
        'is_personal' => true,
    ]);

    $link = Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'url' => 'https://example.com/missing',
        'title' => 'Missing Page',
    ]);

    $service = app(LinkHealthService::class);
    $result = $service->checkLink($link);

    expect($result['status_code'])->toBe(404)
        ->and($result['health_status'])->toBe(LinkHealthStatus::Broken)
        ->and($result['error'])->toContain('404');

    $link->refresh();
    expect($link->http_status)->toBe(404)
        ->and($link->health_status)->toBe(LinkHealthStatus::Broken)
        ->and($link->isBroken())->toBeTrue()
        ->and($link->health_error)->toContain('404');
});

test('it can detect a server error 500 as broken', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.broken.test*' => Http::response('Internal Server Error', 500),
    ]);

    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Error Team',
        'slug' => 'error-team',
        'is_personal' => true,
    ]);

    $link = Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'url' => 'https://api.broken.test',
        'title' => 'Server Error Site',
    ]);

    $service = app(LinkHealthService::class);
    $result = $service->checkLink($link);

    expect($result['status_code'])->toBe(500)
        ->and($result['health_status'])->toBe(LinkHealthStatus::Broken);

    $link->refresh();
    expect($link->isBroken())->toBeTrue()
        ->and($link->http_status)->toBe(500);
});

test('it handles network connection timeouts gracefully', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://timeout.example.com*' => Http::failedConnection(),
    ]);

    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Timeout Team',
        'slug' => 'timeout-team',
        'is_personal' => true,
    ]);

    $link = Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'url' => 'https://timeout.example.com',
        'title' => 'Timeout Site',
    ]);

    $service = app(LinkHealthService::class);
    $result = $service->checkLink($link);

    expect($result['health_status'])->toBe(LinkHealthStatus::Broken)
        ->and($result['error'])->not->toBeNull();

    $link->refresh();
    expect($link->isBroken())->toBeTrue()
        ->and($link->health_error)->not->toBeNull();
});

test('it handles invalid URLs without throwing exceptions', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Invalid URL Team',
        'slug' => 'invalid-url-team',
        'is_personal' => true,
    ]);

    $link = Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'url' => 'not-a-valid-url',
        'title' => 'Bad URL',
    ]);

    $service = app(LinkHealthService::class);
    $result = $service->checkLink($link);

    expect($result['health_status'])->toBe(LinkHealthStatus::Broken)
        ->and($result['error'])->toContain('invalide');
});

test('it can check multiple links for a team and return stats', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://site1.com*' => Http::response('OK', 200),
        'https://site2.com*' => Http::response('Not Found', 404),
    ]);

    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Batch Team',
        'slug' => 'batch-team',
        'is_personal' => true,
    ]);

    Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'url' => 'https://site1.com',
        'title' => 'Site 1',
    ]);

    Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'url' => 'https://site2.com',
        'title' => 'Site 2',
    ]);

    $service = app(LinkHealthService::class);
    $stats = $service->checkTeamLinks($team, force: true);

    expect($stats['total'])->toBe(2)
        ->and($stats['healthy'])->toBe(1)
        ->and($stats['broken'])->toBe(1);
});

test('it executes CheckLinkHealthJob asynchronously', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://queued.test*' => Http::response('OK', 200),
    ]);

    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Job Team',
        'slug' => 'job-team',
        'is_personal' => true,
    ]);

    $link = Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'url' => 'https://queued.test',
        'title' => 'Queued Link',
    ]);

    $job = new CheckLinkHealthJob(link: $link);
    app()->call([$job, 'handle']);

    $link->refresh();
    expect($link->health_status)->toBe(LinkHealthStatus::Healthy)
        ->and($link->http_status)->toBe(200);
});

test('artisan links:check-health command runs successfully', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://cli.test*' => Http::response('OK', 200),
    ]);

    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'CLI Health Team',
        'slug' => 'cli-health-team',
        'is_personal' => true,
    ]);

    Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'url' => 'https://cli.test',
        'title' => 'CLI Link',
    ]);

    $this->artisan('links:check-health', [
        '--team' => $team->id,
        '--force' => true,
    ])->assertSuccessful();

    $this->assertDatabaseHas('links', [
        'team_id' => $team->id,
        'health_status' => 'healthy',
        'http_status' => 200,
    ]);
});
