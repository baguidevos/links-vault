<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SyncSetting;
use App\Models\Team;
use App\Models\User;
use App\Services\Sync\WebSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncController extends Controller
{
    /**
     * Reçoit le lot de synchronisation montant (PUSH) depuis le client Desktop.
     */
    public function push(Request $request, WebSyncService $syncService): JsonResponse
    {
        $validated = $request->validate([
            'team_uuid' => ['nullable', 'string'],
            'team_id' => ['nullable', 'integer'],
            'last_synced_at' => ['nullable', 'date'],
            'entities' => ['required', 'array'],
            'entities.folders' => ['nullable', 'array'],
            'entities.categories' => ['nullable', 'array'],
            'entities.tags' => ['nullable', 'array'],
            'entities.links' => ['nullable', 'array'],
            'entities.deleted' => ['nullable', 'array'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $team = $this->resolveTeam($user, $validated['team_uuid'] ?? null, $validated['team_id'] ?? null);

        if (! $team) {
            return response()->json([
                'error' => 'Équipe introuvable ou accès non autorisé.',
            ], Response::HTTP_FORBIDDEN);
        }

        $result = $syncService->handlePush($user, $team, $validated);

        SyncSetting::updateOrCreate(
            ['user_id' => $user->id],
            [
                'team_id' => $team->id,
                'last_synced_at' => now(),
                'sync_status' => 'active',
                'last_error' => null,
            ]
        );

        return response()->json($result);
    }

    /**
     * Renvoie le delta descendant (PULL) depuis la date demandée.
     */
    public function pull(Request $request, WebSyncService $syncService): JsonResponse
    {
        $validated = $request->validate([
            'team_uuid' => ['nullable', 'string'],
            'team_id' => ['nullable', 'integer'],
            'since' => ['nullable', 'date'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $team = $this->resolveTeam($user, $validated['team_uuid'] ?? null, $validated['team_id'] ?? null);

        if (! $team) {
            return response()->json([
                'error' => 'Équipe introuvable ou accès non autorisé.',
            ], Response::HTTP_FORBIDDEN);
        }

        $result = $syncService->buildPull($user, $team, $validated['since'] ?? null);

        SyncSetting::updateOrCreate(
            ['user_id' => $user->id],
            [
                'team_id' => $team->id,
                'last_synced_at' => now(),
                'sync_status' => 'active',
                'last_error' => null,
            ]
        );

        return response()->json($result);
    }

    /**
     * Résout l'équipe pour l'utilisateur avec vérification des permissions.
     */
    protected function resolveTeam(User $user, ?string $teamUuid = null, ?int $teamId = null): ?Team
    {
        $query = Team::query();

        if ($teamUuid) {
            $query->where('uuid', $teamUuid);
        } elseif ($teamId) {
            $query->where('id', $teamId);
        } elseif ($user->current_team_id) {
            $query->where('id', $user->current_team_id);
        } else {
            $firstTeam = $user->teams()->first();
            if ($firstTeam) {
                return $firstTeam;
            }
        }

        $team = $query->first();

        if (! $team) {
            $team = $user->teams()->first();
        }

        if (! $team) {
            return null;
        }

        $belongsToTeam = $user->teams()->where('teams.id', $team->id)->exists();

        return $belongsToTeam ? $team : null;
    }
}
