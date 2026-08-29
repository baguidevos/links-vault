<?php

use App\Enums\ContentType;
use App\Models\Category;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelDaily\FilaTeams\Models\Team;

uses(RefreshDatabase::class);

test('a user can view the link detail page with rich metadata and actions', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'name' => 'Design Team',
        'slug' => 'design-team',
        'is_personal' => true,
    ]);
    $user->teams()->attach($team->id, ['role' => 'owner']);

    $category = Category::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'name' => 'Apprentissage',
        'slug' => 'apprentissage',
    ]);

    $link = Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'category_id' => $category->id,
        'title' => 'Tutorial Complete de React pour Débutants',
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'description' => 'Un tutoriel complet pour maîtriser React from scratch.',
        'content_type' => ContentType::Youtube,
        'objective' => 'Compléter les sections 1-6 avant vendredi',
        'tags' => 'React,JavaScript,Tutorial',
        'ai_summary' => 'Introduction aux fondamentaux de React et des hooks.',
        'is_favorite' => false,
        'visit_count' => 5,
    ]);

    $response = $this->actingAs($user)
        ->get("/app/{$team->slug}/links/{$link->id}");

    $response->assertOk();
    $response->assertSee('Tutorial Complete de React pour Débutants');
    $response->assertSee('Apprentissage');
    $response->assertSee('Compléter les sections 1-6 avant vendredi');
    $response->assertSee('https://www.youtube.com/embed/dQw4w9WgXcQ');
    $response->assertSee('Résumé IA');
});
