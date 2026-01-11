<?php

use App\Models\Pool;
use App\Services\NHLApiService;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
        'canResetPassword' => Features::enabled(Features::resetPasswords()),
        'status' => session('status'),
    ]);
})->name('home');

Route::get('dashboard', function (NHLApiService $nhlApi) {
    $user = auth()->user();

    // Filter pools based on user role
    $poolsQuery = Pool::with(['ruleSetting', 'users'])
        ->withCount('users');

    if ($user->hasRole('superAdmin')) {
        // SuperAdmin sees all pools
        $poolsQuery = $poolsQuery;
    } elseif ($user->hasRole('poolAdmin')) {
        // PoolAdmin sees ONLY pools they own (created)
        $poolsQuery = $poolsQuery->where('owner_id', $user->id);
    } else {
        // Participants only see pools they're in
        $poolsQuery = $poolsQuery->whereHas('users', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        });
    }

    $pools = $poolsQuery->get()
        ->map(function ($pool) use ($user) {
            // Update status based on dates
            $pool->status = $pool->calculateStatus();
            $pool->save();

            return [
                'id' => $pool->id,
                'name' => $pool->name,
                'status' => $pool->status,
                'participants_count' => $pool->users_count,
                'rule_setting' => $pool->ruleSetting?->name,
                'start_date' => $pool->start_date?->format('Y-m-d'),
                'end_date' => $pool->end_date?->format('Y-m-d'),
                'is_owner' => $pool->owner_id === $user->id,
            ];
        });

    return Inertia::render('dashboard/Index', [
        'pools' => $pools,
        'topScorers' => $nhlApi->getTopScorers(5),
        'ruleSettings' => \App\Models\RuleSetting::all(),
        'canCreatePool' => $user->hasRole(['superAdmin', 'poolAdmin']),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('pools', [App\Http\Controllers\PoolController::class, 'store'])->name('pools.store');

    // Pool player routes
    Route::get('pools/{pool}/players/search', [App\Http\Controllers\PoolPlayerController::class, 'search'])->name('pools.players.search');
    Route::post('pools/{pool}/players', [App\Http\Controllers\PoolPlayerController::class, 'store'])->name('pools.players.store');
    Route::delete('pools/{pool}/players/{poolPlayer}', [App\Http\Controllers\PoolPlayerController::class, 'destroy'])->name('pools.players.destroy');

    // Complete pool selection
    Route::post('pools/{pool}/complete-selection', [App\Http\Controllers\PoolController::class, 'completeSelection'])->name('pools.complete-selection');

    // Pool invitations (poolAdmin only)
    Route::post('pools/{pool}/invitations', [App\Http\Controllers\PoolInvitationController::class, 'store'])->name('pools.invitations.store');
    Route::get('pools/{pool}/invitations', [App\Http\Controllers\PoolInvitationController::class, 'index'])->name('pools.invitations.index');

    // Pool participants management (poolAdmin only)
    Route::delete('pools/{pool}/participants/{user}', [App\Http\Controllers\PoolController::class, 'removeParticipant'])->name('pools.participants.remove');
});

// Public invitation routes (no auth required for registration)
Route::get('invitations/{token}', [App\Http\Controllers\PoolInvitationController::class, 'show'])->name('invitations.show');
Route::post('invitations/{token}/accept', [App\Http\Controllers\PoolInvitationController::class, 'accept'])->name('invitations.accept');

Route::get('pools/{pool}', function (Pool $pool, \App\Services\NHLApiService $nhlApi) {
    $pool->load(['ruleSetting', 'users', 'poolPlayers.user']);

    $currentUser = auth()->user();
    $isPoolAdmin = $currentUser->is_super_admin || $pool->owner_id === $currentUser->id;

    // Get the current user's selection completion status
    $currentUserPivot = $pool->users()->where('users.id', $currentUser->id)->first()?->pivot;
    $userSelectionCompleted = $currentUserPivot?->selection_completed_at !== null;

    // Determine user's individual status
    // If we're past the draft date, force active status
    // Otherwise, check if user has completed their selection
    $now = now()->startOfDay();
    $pastDraftDate = $now->gt($pool->start_date);
    $userStatus = ($pastDraftDate || $userSelectionCompleted) ? 'active' : 'selection';

    // Calculate pool active dates (day after start_date until end_date)
    $poolStartDate = $pool->start_date->copy()->addDay()->format('Y-m-d');
    $poolEndDate = $pool->end_date->copy()->format('Y-m-d');

    // Get rule settings for point calculation
    $ruleSetting = $pool->ruleSetting;

    $selectedPlayers = $pool->poolPlayers->map(function ($poolPlayer) use ($nhlApi, $poolStartDate, $poolEndDate, $currentUser, $userStatus, $ruleSetting) {
        // Get stats for the pool date range only
        $stats = $nhlApi->getPlayerStatsInDateRange(
            $poolPlayer->nhl_player_id,
            $poolStartDate,
            $poolEndDate
        );

        // Calculate points based on rule settings
        $calculatedPoints = 0;

        // For goalies, use goalie-specific stats
        if ($poolPlayer->position === 'G') {
            $goalieStats = $nhlApi->getGoalieStatsInDateRange(
                $poolPlayer->nhl_player_id,
                $poolStartDate,
                $poolEndDate
            );

            $calculatedPoints += ($goalieStats['wins'] ?? 0) * ($ruleSetting->points_per_victory ?? 0);
            $calculatedPoints += ($goalieStats['shutouts'] ?? 0) * ($ruleSetting->points_per_shutout ?? 0);

            // Store goalie stats for display
            $stats['goals'] = 0; // Goalies don't score goals
            $stats['assists'] = 0; // Goalies don't get assists
            $stats['wins'] = $goalieStats['wins'] ?? 0;
            $stats['shutouts'] = $goalieStats['shutouts'] ?? 0;
            $stats['games_played'] = $goalieStats['games_played'] ?? 0;
        } else {
            // For skaters, use goals and assists
            $calculatedPoints += ($stats['goals'] ?? 0) * ($ruleSetting->points_per_goal ?? 0);
            $calculatedPoints += ($stats['assists'] ?? 0) * ($ruleSetting->points_per_assist ?? 0);
        }

        // Get games in pool duration (for reference)
        $gamesInPool = $nhlApi->getTeamGamesInDateRange(
            $poolPlayer->team_abbrev,
            $poolStartDate,
            $poolEndDate
        );

        // Replace the NHL API points with our calculated points
        $stats['points'] = $calculatedPoints;

        return [
            'id' => $poolPlayer->id,
            'nhl_player_id' => $poolPlayer->nhl_player_id,
            'player_name' => $poolPlayer->player_name,
            'position' => $poolPlayer->position,
            'team_abbrev' => $poolPlayer->team_abbrev,
            'team_name' => $poolPlayer->team_name,
            'headshot_url' => $poolPlayer->headshot_url,
            'draft_order' => $poolPlayer->draft_order,
            'games_in_pool' => $gamesInPool,
            'stats' => $stats,
            'can_delete' => $poolPlayer->user_id === $currentUser->id && $userStatus === 'selection',
            'selected_by' => [
                'id' => $poolPlayer->user->id,
                'name' => $poolPlayer->user->name,
            ],
        ];
    })->sortByDesc(function ($player) {
        return $player['stats']['points'];
    })->values();

    // Count current user's players
    $currentUserPlayerCount = $pool->poolPlayers()
        ->where('user_id', $currentUser->id)
        ->count();

    // Get player limits with position limits
    $playerLimits = $pool->ruleSetting->getPlayerLimits();

    // Count current user's players by position
    $positionCounts = [];
    if (! empty($playerLimits['by_position'])) {
        foreach (array_keys($playerLimits['by_position']) as $position) {
            $positionCounts[$position] = $pool->poolPlayers()
                ->where('user_id', $currentUser->id)
                ->where('position', $position)
                ->count();
        }
    }

    // Calculate participant statistics
    $participants = $pool->users->map(function ($user) use ($pool, $nhlApi, $poolStartDate, $poolEndDate, $ruleSetting) {
        $userPlayers = $pool->poolPlayers()->where('user_id', $user->id)->get();

        $totalGoals = 0;
        $totalAssists = 0;
        $totalPoints = 0;
        $totalPlusMinus = 0;

        foreach ($userPlayers as $poolPlayer) {
            // Get stats for the pool date range for each player
            $stats = $nhlApi->getPlayerStatsInDateRange(
                $poolPlayer->nhl_player_id,
                $poolStartDate,
                $poolEndDate
            );

            // Calculate points based on rule settings
            $playerPoints = 0;

            // For goalies, use goalie-specific stats
            if ($poolPlayer->position === 'G') {
                $goalieStats = $nhlApi->getGoalieStatsInDateRange(
                    $poolPlayer->nhl_player_id,
                    $poolStartDate,
                    $poolEndDate
                );

                $playerPoints += ($goalieStats['wins'] ?? 0) * ($ruleSetting->points_per_victory ?? 0);
                $playerPoints += ($goalieStats['shutouts'] ?? 0) * ($ruleSetting->points_per_shutout ?? 0);
                // Goalies don't contribute to goals/assists stats
            } else {
                // For skaters, use goals and assists
                $totalGoals += $stats['goals'] ?? 0;
                $totalAssists += $stats['assists'] ?? 0;
                $totalPlusMinus += $stats['plus_minus'] ?? 0;

                $playerPoints += ($stats['goals'] ?? 0) * ($ruleSetting->points_per_goal ?? 0);
                $playerPoints += ($stats['assists'] ?? 0) * ($ruleSetting->points_per_assist ?? 0);
            }

            $totalPoints += $playerPoints;
        }

        $activePlayers = $userPlayers->count();
        $injuredPlayers = 0; // TODO: Implement injury tracking

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'total_goals' => $totalGoals,
            'total_assists' => $totalAssists,
            'total_points' => $totalPoints,
            'total_plus_minus' => $totalPlusMinus,
            'active_players' => $activePlayers,
            'injured_players' => $injuredPlayers,
            'is_owner' => $pool->owner_id === $user->id,
        ];
    })->sortByDesc('total_points')->values();

    return Inertia::render('pools/Show', [
        'pool' => [
            'id' => $pool->id,
            'name' => $pool->name,
            'status' => $userStatus,
            'start_date' => $pool->start_date?->format('Y-m-d'),
            'end_date' => $pool->end_date?->format('Y-m-d'),
            'rule_setting' => $pool->ruleSetting,
            'users' => $pool->users,
            'is_admin' => $isPoolAdmin,
            'selected_players' => $selectedPlayers,
            'current_user_player_count' => $currentUserPlayerCount,
            'max_players_per_user' => $playerLimits['max_per_user'] ?? 20,
            'position_limits' => $playerLimits['by_position'] ?? null,
            'position_counts' => $positionCounts,
            'participants' => $participants,
        ],
    ]);
})->middleware(['auth', 'verified'])->name('pools.show');

require __DIR__.'/settings.php';
