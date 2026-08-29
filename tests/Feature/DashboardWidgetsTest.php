<?php

use App\Filament\Widgets\LatestLinksWidget;
use App\Filament\Widgets\LinksByCategoryChart;
use App\Filament\Widgets\LinksContentTypeChart;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Models\Category;
use App\Models\Link;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelDaily\FilaTeams\Models\Team;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('dashboard widgets render correctly for tenant team', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'name' => 'Analytics Workspace',
        'slug' => 'analytics-workspace',
        'is_personal' => true,
    ]);
    $user->teams()->attach($team->id, ['role' => 'owner']);
    auth()->login($user);
    Filament::setTenant($team);

    $category = Category::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'name' => 'Dev & Tech',
        'slug' => 'dev-tech',
    ]);

    Link::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'category_id' => $category->id,
        'title' => 'Laravel Documentation',
        'url' => 'https://laravel.com/docs',
        'visit_count' => 42,
        'is_favorite' => true,
    ]);

    Livewire::actingAs($user)
        ->test(StatsOverviewWidget::class)
        ->assertSee('Liens Enregistrés')
        ->assertSee('Total des Visites')
        ->assertSee('42');

    Livewire::actingAs($user)
        ->test(LatestLinksWidget::class)
        ->assertSee('Laravel Documentation')
        ->assertSee('Dev & Tech');

    Livewire::actingAs($user)
        ->test(LinksByCategoryChart::class)
        ->assertOk();

    Livewire::actingAs($user)
        ->test(LinksContentTypeChart::class)
        ->assertOk();
});
