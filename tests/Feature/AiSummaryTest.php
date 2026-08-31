<?php

declare(strict_types=1);

use App\Actions\LinkActions\CreateLinkAction;
use App\Actions\LinkActions\GenerateAiSummaryAction;
use App\Ai\Agents\LinkSummaryAgent;
use App\Enums\ContentType;
use App\Enums\LinkVisibility;
use App\Jobs\GenerateLinkAiSummaryJob;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use LaravelDaily\FilaTeams\Models\Team;

uses(RefreshDatabase::class);

test('LinkSummaryAgent can be faked and returns structured summary data', function () {
    LinkSummaryAgent::fake([
        [
            'summary' => 'Un article exceptionnel sur l\'architecture Laravel 13 et ses nouveautés.',
            'key_takeaways' => [
                'Nouveaux composants optimisés pour PHP 8.4',
                'Support natif du Laravel AI SDK',
                'Performance décuplée sur SQLite et Postgres',
            ],
            'suggested_tags' => ['laravel', 'php', 'architecture'],
            'category' => 'Technology',
        ],
    ]);

    $user = User::factory()->create();
    $team = Team::create(['name' => 'Tech Team', 'slug' => 'tech-team', 'is_personal' => true]);
    $user->teams()->attach($team->id, ['role' => 'owner']);

    $link = Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'title' => 'Guide Complet Laravel 13',
        'url' => 'https://laravel.com/docs/13.x',
        'description' => 'Documentation officielle de Laravel.',
        'content_type' => ContentType::Article,
        'visibility' => LinkVisibility::Private,
    ]);

    $updatedLink = GenerateAiSummaryAction::execute($link);

    expect($updatedLink->ai_summary_status)->toBe('completed')
        ->and($updatedLink->ai_summary)->toContain('Un article exceptionnel sur l\'architecture Laravel 13')
        ->and($updatedLink->ai_summary)->toContain('Points essentiels')
        ->and($updatedLink->ai_summary)->toContain('Nouveaux composants optimisés pour PHP 8.4')
        ->and($updatedLink->metadata['ai_analysis']['summary'])->toBe('Un article exceptionnel sur l\'architecture Laravel 13 et ses nouveautés.')
        ->and($updatedLink->tags()->pluck('name')->all())->toEqual(['laravel', 'php', 'architecture']);
});

test('GenerateLinkAiSummaryJob executes GenerateAiSummaryAction', function () {
    LinkSummaryAgent::fake([
        [
            'summary' => 'Vidéo explicative sur Livewire 4 et Alpine.',
            'key_takeaways' => ['Réactivité instantanée', 'Moins de JavaScript à écrire', 'Intégration Blade parfaite'],
            'suggested_tags' => ['livewire', 'alpinejs'],
            'category' => 'Tutorial',
        ],
    ]);

    $user = User::factory()->create();
    $team = Team::create(['name' => 'Video Team', 'slug' => 'video-team', 'is_personal' => true]);
    $user->teams()->attach($team->id, ['role' => 'owner']);

    $link = Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'title' => 'Tutoriel Livewire 4',
        'url' => 'https://www.youtube.com/watch?v=12345678901',
        'content_type' => ContentType::Youtube,
    ]);

    $job = new GenerateLinkAiSummaryJob($link);
    $job->handle();

    $link->refresh();
    expect($link->ai_summary_status)->toBe('completed')
        ->and($link->ai_summary)->toContain('Vidéo explicative sur Livewire 4')
        ->and($link->tags()->pluck('name')->all())->toEqual(['livewire', 'alpinejs']);
});

test('CreateLinkAction dispatches GenerateLinkAiSummaryJob when requested', function () {
    Queue::fake();

    $user = User::factory()->create();
    $team = Team::create(['name' => 'Main Team', 'slug' => 'main-team', 'is_personal' => true]);
    $user->teams()->attach($team->id, ['role' => 'owner']);

    $link = CreateLinkAction::execute([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'url' => 'https://example.com/test-ai-summary',
        'title' => 'Lien avec résumé IA',
        'generate_ai_summary' => true,
    ]);

    Queue::assertPushed(GenerateLinkAiSummaryJob::class, function ($job) use ($link) {
        return $job->link->id === $link->id;
    });
});

test('GenerateAiSummaryAction gracefully marks status as failed on error', function () {
    // When fake is set to throw or agent fails
    LinkSummaryAgent::fake(function () {
        throw new RuntimeException('API rate limit reached');
    });

    $user = User::factory()->create();
    $team = Team::create(['name' => 'Fail Team', 'slug' => 'fail-team', 'is_personal' => true]);
    $user->teams()->attach($team->id, ['role' => 'owner']);

    $link = Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'title' => 'Lien test erreur',
        'url' => 'https://example.com/error-test',
    ]);

    $updatedLink = GenerateAiSummaryAction::execute($link);

    expect($updatedLink->ai_summary_status)->toBe('failed');
});
