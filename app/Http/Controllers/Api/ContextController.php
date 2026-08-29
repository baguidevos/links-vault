<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContextController extends Controller
{
    /**
     * Get contextual data (teams, categories, tags) for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $teams = collect();
        if (method_exists($user, 'teams')) {
            try {
                $teams = $user->teams()->get(['teams.id', 'teams.name', 'teams.slug', 'teams.is_personal']);
            } catch (\Throwable $e) {
                // Ignore if relation fails
            }
        }

        $currentTeam = $user->currentTeam ?: $teams->first();
        $teamId = $request->query('team_id', $currentTeam?->id);

        // Load categories
        $categoriesQuery = Category::query();
        if ($teamId) {
            $categoriesQuery->where('team_id', $teamId);
        } else {
            $categoriesQuery->where('user_id', $user->id);
        }
        $categories = $categoriesQuery->orderBy('sort_order')->orderBy('name')->get([
            'id', 'team_id', 'name', 'slug', 'color', 'icon',
        ]);

        // Load tags
        $tagsQuery = Tag::query();
        if ($teamId) {
            $tagsQuery->where('team_id', $teamId);
        } else {
            $tagsQuery->where('user_id', $user->id);
        }
        $tags = $tagsQuery->orderBy('name')->get(['id', 'team_id', 'name', 'slug']);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'current_team_id' => $currentTeam?->id,
            'teams' => $teams,
            'categories' => $categories,
            'tags' => $tags,
        ]);
    }
}
