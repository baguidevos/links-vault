<?php

declare(strict_types=1);

use App\Models\Link;
use App\Models\LinkShare;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('visiting a link via /links/{id}/visit increments visit count and updates last_visited_at', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Design Team',
        'slug' => 'design-team',
        'is_personal' => true,
    ]);

    $link = Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'title' => 'Laravel Framework',
        'url' => 'https://laravel.com',
        'url_hash' => hash('sha256', 'https://laravel.com'),
        'visit_count' => 0,
        'last_visited_at' => null,
    ]);

    $response = $this->get(route('links.visit', $link));

    $response->assertRedirect('https://laravel.com');

    $link->refresh();
    expect($link->visit_count)->toBe(1)
        ->and($link->last_visited_at)->not->toBeNull();
});

test('accessing a shared link via /share/{token} tracks opening, clicking and increments visit count', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $team = Team::create([
        'user_id' => $sender->id,
        'name' => 'Team Pro',
        'slug' => 'team-pro',
        'is_personal' => true,
    ]);

    $link = Link::create([
        'user_id' => $sender->id,
        'team_id' => $team->id,
        'title' => 'PHP Documentation',
        'url' => 'https://www.php.net',
        'url_hash' => hash('sha256', 'https://www.php.net'),
        'visit_count' => 2,
    ]);

    $token = Str::random(32);
    $share = LinkShare::create([
        'link_id' => $link->id,
        'sender_user_id' => $sender->id,
        'recipient_user_id' => $recipient->id,
        'recipient_email' => $recipient->email,
        'token' => $token,
        'status' => 'sent',
        'expires_at' => now()->addDays(7),
        'sent_at' => now(),
    ]);

    $response = $this->get(route('links.share.redirect', ['token' => $token]));

    $response->assertRedirect('https://www.php.net');

    $share->refresh();
    $link->refresh();

    expect($share->status)->toBe('clicked')
        ->and($share->opened_at)->not->toBeNull()
        ->and($share->clicked_at)->not->toBeNull()
        ->and($link->visit_count)->toBe(3)
        ->and($link->last_visited_at)->not->toBeNull();
});

test('accessing an expired shared link returns 410 gone and displays expired notice', function () {
    $sender = User::factory()->create();
    $team = Team::create([
        'user_id' => $sender->id,
        'name' => 'Team Expired',
        'slug' => 'team-expired',
        'is_personal' => true,
    ]);

    $link = Link::create([
        'user_id' => $sender->id,
        'team_id' => $team->id,
        'title' => 'Ancien lien',
        'url' => 'https://archive.org',
        'url_hash' => hash('sha256', 'https://archive.org'),
    ]);

    $token = Str::random(32);
    LinkShare::create([
        'link_id' => $link->id,
        'sender_user_id' => $sender->id,
        'recipient_email' => 'contact@test.com',
        'token' => $token,
        'status' => 'sent',
        'expires_at' => now()->subDay(), // Expired
        'sent_at' => now()->subDays(2),
    ]);

    $response = $this->get(route('links.share.redirect', ['token' => $token]));

    $response->assertStatus(410);
});

test('accessing a non-existent shared link token returns 404', function () {
    $response = $this->get(route('links.share.redirect', ['token' => 'non-existent-token-12345']));

    $response->assertNotFound();
});
