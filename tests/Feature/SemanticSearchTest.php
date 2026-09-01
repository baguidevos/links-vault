<?php

declare(strict_types=1);

use App\Jobs\GenerateLinkEmbeddingJob;
use App\Models\Link;
use App\Models\Team;
use App\Models\User;
use App\Services\SemanticSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;

uses(RefreshDatabase::class);

test('it can generate and index vector embeddings for a link', function () {
    Embeddings::fake([
        [[0.1, 0.2, 0.3, 0.4, 0.5]],
    ]);

    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Tech Team',
        'slug' => 'tech-team',
        'is_personal' => true,
    ]);

    $link = Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'url' => 'https://laravel.com/docs',
        'url_hash' => hash('sha256', 'https://laravel.com/docs'),
        'title' => 'Laravel documentation and ecosystem guide',
        'description' => 'Official docs',
    ]);

    $service = app(SemanticSearchService::class);
    $result = $service->indexLink($link);

    expect($result)->toBeTrue();
    $link->refresh();

    expect($link->hasEmbedding())->toBeTrue()
        ->and($link->embedding)->toBeArray()
        ->and(count($link->embedding))->toBe(5)
        ->and($link->embedding_generated_at)->not->toBeNull();
});

test('it calculates cosine similarity accurately between vectors', function () {
    $service = app(SemanticSearchService::class);

    // Identical vectors -> similarity 1.0
    $vecA = [1.0, 0.0, 0.0];
    $vecB = [1.0, 0.0, 0.0];
    expect($service->cosineSimilarity($vecA, $vecB))->toEqualWithDelta(1.0, 0.001);

    // Orthogonal vectors -> similarity 0.0
    $vecC = [0.0, 1.0, 0.0];
    expect($service->cosineSimilarity($vecA, $vecC))->toEqualWithDelta(0.0, 0.001);

    // Opposite vectors -> similarity -1.0
    $vecD = [-1.0, 0.0, 0.0];
    expect($service->cosineSimilarity($vecA, $vecD))->toEqualWithDelta(-1.0, 0.001);
});

test('it performs semantic search and ranks links by similarity score', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'AI Team',
        'slug' => 'ai-team',
        'is_personal' => true,
    ]);

    // Vector for docker tutorial: [0.9, 0.1, 0.0]
    $linkDocker = Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'url' => 'https://docker.test/tutorial',
        'url_hash' => hash('sha256', 'https://docker.test/tutorial'),
        'title' => 'Docker tutorial for beginners',
        'embedding' => [0.9, 0.1, 0.0],
        'embedding_generated_at' => now(),
    ]);

    // Vector for cooking recipe: [0.0, 0.1, 0.9]
    $linkCooking = Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'url' => 'https://recipes.test/pasta',
        'url_hash' => hash('sha256', 'https://recipes.test/pasta'),
        'title' => 'Italian Pasta Recipe',
        'embedding' => [0.0, 0.1, 0.9],
        'embedding_generated_at' => now(),
    ]);

    // Fake query "containerizing app" embedding close to Docker: [0.85, 0.15, 0.0]
    Embeddings::fake([
        [[0.85, 0.15, 0.0]],
    ]);

    $service = app(SemanticSearchService::class);
    $results = $service->search('containerizing application', $team, limit: 5, minSimilarity: 0.2);

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($linkDocker->id)
        ->and($results->first()->similarity_percentage)->toBeGreaterThan(90);
});

test('it scopes semantic search to tenant team workspace', function () {
    $user = User::factory()->create();
    $teamA = Team::create(['user_id' => $user->id, 'name' => 'Team A', 'slug' => 'team-a', 'is_personal' => true]);
    $teamB = Team::create(['user_id' => $user->id, 'name' => 'Team B', 'slug' => 'team-b', 'is_personal' => false]);

    $linkTeamA = Link::create([
        'user_id' => $user->id,
        'team_id' => $teamA->id,
        'url' => 'https://teama.test',
        'url_hash' => hash('sha256', 'https://teama.test'),
        'title' => 'Team A Link',
        'embedding' => [0.8, 0.2, 0.0],
    ]);

    $linkTeamB = Link::create([
        'user_id' => $user->id,
        'team_id' => $teamB->id,
        'url' => 'https://teamb.test',
        'url_hash' => hash('sha256', 'https://teamb.test'),
        'title' => 'Team B Link',
        'embedding' => [0.8, 0.2, 0.0],
    ]);

    Embeddings::fake([
        [[0.8, 0.2, 0.0]],
    ]);

    $service = app(SemanticSearchService::class);
    $resultsTeamA = $service->search('test query', $teamA);

    expect($resultsTeamA)->toHaveCount(1)
        ->and($resultsTeamA->first()->id)->toBe($linkTeamA->id);
});

test('it executes GenerateLinkEmbeddingJob asynchronously', function () {
    Queue::fake();

    $user = User::factory()->create();
    $team = Team::create(['user_id' => $user->id, 'name' => 'Queue Team', 'slug' => 'queue-team', 'is_personal' => true]);

    $link = Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'url' => 'https://queue.test',
        'url_hash' => hash('sha256', 'https://queue.test'),
        'title' => 'Queue Link',
    ]);

    GenerateLinkEmbeddingJob::dispatch($link);

    Queue::assertPushed(GenerateLinkEmbeddingJob::class, function ($job) use ($link) {
        return $job->link->id === $link->id;
    });
});

test('artisan links:generate-embeddings command runs successfully', function () {
    Embeddings::fake([
        [[0.1, 0.2, 0.3]],
    ]);

    $user = User::factory()->create();
    $team = Team::create(['user_id' => $user->id, 'name' => 'CLI Team', 'slug' => 'cli-team', 'is_personal' => true]);

    Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'url' => 'https://cli.test',
        'url_hash' => hash('sha256', 'https://cli.test'),
        'title' => 'CLI Link',
    ]);

    $this->artisan('links:generate-embeddings', ['--team' => $team->id])
        ->assertSuccessful();

    $link = Link::where('url', 'https://cli.test')->first();
    expect($link->hasEmbedding())->toBeTrue();
});

test('it renders semantic search header action on ListLinks page for authenticated user', function () {
    $user = User::factory()->create();
    $team = Team::create(['user_id' => $user->id, 'name' => 'UI Team', 'slug' => 'ui-team', 'is_personal' => true]);
    $user->teams()->attach($team->id, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $this->actingAs($user)
        ->get("/app/{$team->slug}/links")
        ->assertSuccessful()
        ->assertSee('Recherche IA');
});
