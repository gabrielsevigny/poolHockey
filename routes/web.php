<?php

use App\Models\Pool;
use App\Services\NHLApiService;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function (NHLApiService $nhlApi) {
    $pools = Pool::with(['ruleSetting', 'users'])
        ->withCount('users')
        ->get()
        ->map(function ($pool) {
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
            ];
        });

    return Inertia::render('dashboard/Index', [
        'pools' => $pools,
        'topScorers' => $nhlApi->getTopScorers(5),
        'ruleSettings' => \App\Models\RuleSetting::all(),
        'users' => \App\Models\User::select('id', 'name', 'email')->get(),
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
});

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

    $selectedPlayers = $pool->poolPlayers->map(function ($poolPlayer) use ($nhlApi, $poolStartDate, $poolEndDate, $currentUser, $userStatus) {
        // Get stats for the pool date range only
        $stats = $nhlApi->getPlayerStatsInDateRange(
            $poolPlayer->nhl_player_id,
            $poolStartDate,
            $poolEndDate
        );

        // Get games in pool duration (for reference)
        $gamesInPool = $nhlApi->getTeamGamesInDateRange(
            $poolPlayer->team_abbrev,
            $poolStartDate,
            $poolEndDate
        );

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
        ],
    ]);
})->middleware(['auth', 'verified'])->name('pools.show');

require __DIR__.'/settings.php';
