<?php

namespace App\Http\Controllers;

use App\Events\PlayerRemoved;
use App\Events\PlayerSelected;
use App\Models\Pool;
use App\Models\PoolPlayer;
use App\Services\NHLApiService;
use Illuminate\Http\Request;

class PoolPlayerController extends Controller
{
    public function __construct(private NHLApiService $nhlApi) {}

    /**
     * Search for NHL players.
     */
    public function search(Request $request, Pool $pool)
    {
        $query = $request->input('query', '');

        if (strlen($query) < 2) {
            return response()->json([
                'data' => [],
                'total' => 0,
            ]);
        }

        $results = $this->nhlApi->searchPlayers($query, 50);

        // Get already selected player IDs in this pool
        $selectedPlayerIds = PoolPlayer::where('pool_id', $pool->id)
            ->pluck('nhl_player_id')
            ->toArray();

        // Add games count and availability status to each player
        $enrichedData = collect($results['data'])->map(function ($player) use ($pool, $selectedPlayerIds) {
            $gamesInPool = 0;

            if ($player['team_abbrev']) {
                $gamesInPool = $this->nhlApi->getTeamGamesInDateRange(
                    $player['team_abbrev'],
                    $pool->start_date->addDay()->format('Y-m-d'), // Pool starts day after start_date
                    $pool->end_date->format('Y-m-d')
                );
            }

            return array_merge($player, [
                'games_in_pool' => $gamesInPool,
                'is_available' => ! in_array($player['id'], $selectedPlayerIds),
                'selected_by' => in_array($player['id'], $selectedPlayerIds)
                    ? PoolPlayer::where('pool_id', $pool->id)
                        ->where('nhl_player_id', $player['id'])
                        ->with('user:id,name')
                        ->first()
                        ?->user
                        ?->name
                    : null,
            ]);
        })->toArray();

        return response()->json([
            'data' => $enrichedData,
            'total' => count($enrichedData),
        ]);
    }

    /**
     * Add a player to the pool.
     */
    public function store(Request $request, Pool $pool)
    {
        $validated = $request->validate([
            'nhl_player_id' => ['required', 'integer'],
            'player_name' => ['required', 'string'],
            'position' => ['required', 'string'],
            'team_abbrev' => ['required', 'string'],
            'team_name' => ['required', 'string'],
            'headshot_url' => ['nullable', 'string'],
        ], [
            'nhl_player_id.required' => 'L\'identifiant du joueur est requis.',
            'player_name.required' => 'Le nom du joueur est requis.',
            'position.required' => 'La position du joueur est requise.',
            'team_abbrev.required' => 'L\'équipe du joueur est requise.',
        ]);

        // Check if player is already selected in this pool
        $existingSelection = PoolPlayer::where('pool_id', $pool->id)
            ->where('nhl_player_id', $validated['nhl_player_id'])
            ->first();

        if ($existingSelection) {
            return response()->json([
                'message' => 'Ce joueur a déjà été sélectionné dans ce pool.',
                'selected_by' => $existingSelection->user->name,
            ], 422);
        }

        // Check position limits
        $playerLimits = $pool->ruleSetting->getPlayerLimits();
        $positionLimits = $playerLimits['by_position'] ?? [];

        if (! empty($positionLimits) && isset($positionLimits[$validated['position']])) {
            $maxForPosition = $positionLimits[$validated['position']]['max'] ?? null;

            if ($maxForPosition !== null && $maxForPosition > 0) {
                // Count current players at this position for this user
                $currentCount = PoolPlayer::where('pool_id', $pool->id)
                    ->where('user_id', $request->user()->id)
                    ->where('position', $validated['position'])
                    ->count();

                if ($currentCount >= $maxForPosition) {
                    return response()->json([
                        'message' => "Vous avez atteint la limite maximale de {$maxForPosition} joueur(s) pour la position {$validated['position']}.",
                    ], 422);
                }
            }
        }

        // Get next draft order
        $draftOrder = PoolPlayer::where('pool_id', $pool->id)
            ->max('draft_order') + 1;

        try {
            $poolPlayer = PoolPlayer::create([
                'pool_id' => $pool->id,
                'user_id' => $request->user()->id,
                'nhl_player_id' => $validated['nhl_player_id'],
                'player_name' => $validated['player_name'],
                'position' => $validated['position'],
                'team_abbrev' => $validated['team_abbrev'],
                'team_name' => $validated['team_name'],
                'headshot_url' => $validated['headshot_url'],
                'draft_order' => $draftOrder ?? 1,
            ]);

            // Broadcast event to notify all users in this pool
            broadcast(new PlayerSelected(
                poolId: $pool->id,
                nhlPlayerId: $validated['nhl_player_id'],
                playerName: $validated['player_name'],
                selectedBy: $request->user()->name,
            ))->toOthers();

            return response()->json([
                'message' => 'Joueur ajouté avec succès.',
                'player' => $poolPlayer,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Une erreur est survenue lors de l\'ajout du joueur.',
            ], 500);
        }
    }

    /**
     * Remove a player from the pool.
     */
    public function destroy(Pool $pool, PoolPlayer $poolPlayer)
    {
        // Ensure the player belongs to this pool and the current user
        if ($poolPlayer->pool_id !== $pool->id) {
            return response()->json([
                'message' => 'Ce joueur n\'appartient pas à ce pool.',
            ], 403);
        }

        if ($poolPlayer->user_id !== auth()->id()) {
            return response()->json([
                'message' => 'Vous ne pouvez pas retirer ce joueur.',
            ], 403);
        }

        $nhlPlayerId = $poolPlayer->nhl_player_id;
        $poolPlayer->delete();

        // Broadcast event to notify all users in this pool
        broadcast(new PlayerRemoved(
            poolId: $pool->id,
            nhlPlayerId: $nhlPlayerId,
        ))->toOthers();

        return response()->json([
            'message' => 'Joueur retiré avec succès.',
        ]);
    }
}
