<?php

use App\Models\Category;
use App\Models\Link;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelDaily\FilaTeams\Models\Team;

uses(RefreshDatabase::class);

test('it can authenticate and receive an API token', function () {
    $user = User::factory()->create([
        'email' => 'test@linksvault.app',
        'password' => 'secret123',
    ]);

    $response = $this->postJson(route('api.auth.token'), [
        'email' => 'test@linksvault.app',
        'password' => 'secret123',
        'device_name' => 'Chrome Extension Test',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'token',
            'user' => ['id', 'name', 'email'],
            'message',
        ]);
});

test('it returns user context including teams, categories and tags', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'name' => 'Mon Espace',
        'slug' => 'mon-espace',
        'user_id' => $user->id,
        'is_personal' => true,
    ]);
    $user->teams()->attach($team->id, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $category = Category::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'name' => 'Dev & Tech',
        'slug' => 'dev-tech',
        'color' => '#3B82F6',
    ]);

    $tag = Tag::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'name' => 'Laravel',
        'slug' => 'laravel',
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson(route('api.context'));

    $response->assertOk()
        ->assertJsonPath('current_team_id', $team->id)
        ->assertJsonPath('categories.0.name', 'Dev & Tech')
        ->assertJsonPath('tags.0.name', 'Laravel');
});

test('it can store a link from the API', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'name' => 'Mon Espace',
        'slug' => 'mon-espace',
        'user_id' => $user->id,
        'is_personal' => true,
    ]);
    $user->teams()->attach($team->id, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $category = Category::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'name' => 'Tutorials',
        'slug' => 'tutorials',
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson(route('api.links.store'), [
        'url' => 'https://laravel.com/docs',
        'title' => 'Laravel Documentation',
        'description' => 'Official docs for Laravel Framework',
        'category_id' => $category->id,
        'tags' => ['php', 'framework'],
        'is_favorite' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('link.title', 'Laravel Documentation')
        ->assertJsonPath('link.is_favorite', true);

    expect(Link::where('url', 'https://laravel.com/docs')->exists())->toBeTrue();
});
