<?php

declare(strict_types=1);

use App\Actions\LinkActions\CreateLinkAction;
use App\Models\Link;
use App\Models\Team;
use App\Models\User;
use App\Services\SubscriptionQuotaService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Nafiswatsiq\Subbase\Models\Plan;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

test('it automatically assigns free plan to user without subscription', function () {
    $user = User::factory()->create();
    $service = app(SubscriptionQuotaService::class);

    $plan = $service->getCurrentPlan($user);

    expect($plan->slug)->toBe('free')
        ->and($service->getLinksLimit($user))->toBe(100)
        ->and($service->canUseCloudBackup($user))->toBeFalse();
});

test('it allows link creation when under links limit on free plan', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Free Team',
        'slug' => 'free-team',
        'is_personal' => true,
    ]);
    $user->update(['current_team_id' => $team->id]);

    $service = app(SubscriptionQuotaService::class);
    expect($service->canCreateLink($user, $team))->toBeTrue();

    $link = CreateLinkAction::execute([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'url' => 'https://example.com',
        'title' => 'Example Link',
    ]);

    expect($link)->toBeInstanceOf(Link::class)
        ->and($link->url)->toBe('https://example.com');
});

test('it prevents link creation when links limit is reached on free plan', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Limit Team',
        'slug' => 'limit-team',
        'is_personal' => true,
    ]);
    $user->update(['current_team_id' => $team->id]);

    // Create 100 links to hit the limit
    for ($i = 1; $i <= 100; $i++) {
        Link::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'url' => "https://example.com/{$i}",
            'url_hash' => hash('sha256', "https://example.com/{$i}"),
            'title' => "Link {$i}",
        ]);
    }

    $service = app(SubscriptionQuotaService::class);
    expect($service->canCreateLink($user, $team))->toBeFalse();

    $this->expectException(ValidationException::class);

    CreateLinkAction::execute([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'url' => 'https://example.com/101',
        'title' => 'Link 101',
    ]);
});

test('it allows unlimited link creation when user upgrades to pro plan', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Pro Team',
        'slug' => 'pro-team',
        'is_personal' => true,
    ]);
    $user->update(['current_team_id' => $team->id]);

    // Upgrade to Pro
    $service = app(SubscriptionQuotaService::class);
    $service->subscribeToPlan($user, 'pro');

    expect($service->getLinksLimit($user))->toBe('unlimited')
        ->and($service->canUseCloudBackup($user))->toBeTrue()
        ->and($service->canCreateLink($user, $team))->toBeTrue();
});

test('it tracks and enforces monthly AI summary quota', function () {
    $user = User::factory()->create();
    $service = app(SubscriptionQuotaService::class);

    expect($service->canGenerateAiSummary($user))->toBeTrue();

    // Consume 10 AI summaries (free plan limit)
    for ($i = 0; $i < 10; $i++) {
        $service->consumeAiSummary($user);
    }

    expect($service->canGenerateAiSummary($user))->toBeFalse();

    // Upgrade to Pro
    $service->subscribeToPlan($user, 'pro');
    expect($service->canGenerateAiSummary($user))->toBeTrue();
});

test('it calculates usage statistics correctly', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Stats Team',
        'slug' => 'stats-team',
        'is_personal' => true,
    ]);
    $user->update(['current_team_id' => $team->id]);

    Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'url' => 'https://stats.test',
        'url_hash' => hash('sha256', 'https://stats.test'),
        'title' => 'Stats Link',
    ]);

    $service = app(SubscriptionQuotaService::class);
    $stats = $service->getUsageStats($user, $team);

    expect($stats['links']['used'])->toBe(1)
        ->and($stats['links']['limit'])->toBe(100)
        ->and($stats['links']['percentage'])->toBe(1)
        ->and($stats['cloud_backup'])->toBeFalse();
});

test('it renders manage subscription page for authenticated user', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Sub Page Team',
        'slug' => 'sub-page-team',
        'is_personal' => true,
    ]);
    $user->teams()->attach($team->id, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $this->actingAs($user)
        ->get("/app/{$team->slug}/manage-subscription")
        ->assertSuccessful()
        ->assertSee('Gérer mon Abonnement')
        ->assertSee('Gratuit')
        ->assertSee('Pro');
});
