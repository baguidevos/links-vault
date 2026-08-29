<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use LaravelDaily\FilaTeams\Models\Team;
use LaravelDaily\FilaTeams\Models\TeamInvitation;
use LaravelDaily\FilaTeams\Notifications\TeamInvitationNotification;

uses(RefreshDatabase::class);

test('team invitation notification is dispatched', function () {
    Notification::fake();

    $user = User::factory()->create();
    $team = Team::create([
        'name' => 'Mailpit Team',
        'slug' => 'mailpit-team',
        'is_personal' => false,
    ]);

    $invitation = TeamInvitation::create([
        'team_id' => $team->id,
        'email' => 'invitee@example.com',
        'role' => 'member',
        'code' => 'test-invitation-code',
        'invited_by' => $user->id,
        'expires_at' => now()->addDays(7),
    ]);

    Notification::route('mail', 'invitee@example.com')
        ->notify(new TeamInvitationNotification($invitation));

    Notification::assertSentOnDemand(TeamInvitationNotification::class);
});
