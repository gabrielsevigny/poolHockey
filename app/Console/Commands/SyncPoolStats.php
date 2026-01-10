<?php

namespace App\Console\Commands;

use App\Events\StatsUpdated;
use App\Models\Pool;
use App\Services\NHLApiService;
use Illuminate\Console\Command;

class SyncPoolStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pools:sync-stats {--pool= : Sync stats for a specific pool ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize NHL player stats for all active pools';

    /**
     * Execute the console command.
     */
    public function handle(NHLApiService $nhlApi): int
    {
        $poolId = $this->option('pool');

        $pools = $poolId
            ? Pool::where('id', $poolId)->get()
            : Pool::whereIn('status', ['selection', 'active'])->get();

        if ($pools->isEmpty()) {
            $this->info('No pools to sync.');

            return Command::SUCCESS;
        }

        $this->info("Syncing stats for {$pools->count()} pool(s)...");

        foreach ($pools as $pool) {
            $this->info("Syncing pool: {$pool->name} (ID: {$pool->id})");

            $poolPlayers = $pool->poolPlayers()->with('user')->get();

            foreach ($poolPlayers as $poolPlayer) {
                try {
                    // Get updated stats from NHL API
                    $stats = $nhlApi->getPlayerStatsInDateRange(
                        $poolPlayer->nhl_player_id,
                        $pool->start_date->addDay()->format('Y-m-d'),
                        $pool->end_date->format('Y-m-d')
                    );

                    // You could store these stats in the database if needed
                    // For now, we just trigger the update event

                    $this->line("  ✓ {$poolPlayer->player_name}: {$stats['goals']}G {$stats['assists']}A = {$stats['points']}PTS");
                } catch (\Exception $e) {
                    $this->error("  ✗ Error syncing {$poolPlayer->player_name}: {$e->getMessage()}");
                }
            }

            // Broadcast that stats have been updated for this pool
            broadcast(new StatsUpdated($pool->id));

            $this->info("  → Stats updated for pool {$pool->id}");
        }

        $this->info('✓ Sync completed!');

        return Command::SUCCESS;
    }
}
