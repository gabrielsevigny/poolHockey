<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class NHLApiService
{
    private const BASE_URL = 'https://api.nhle.com/stats/rest/en';

    private const CURRENT_SEASON = '20252026';

    /**
     * Get top scorers for the current week.
     */
    public function getTopScorers(int $limit = 5): array
    {
        return Cache::remember('nhl_top_scorers', now()->addHours(6), function () use ($limit) {
            // Calculate the current week's date range (Monday to Sunday)
            $now = now();
            $startOfWeek = $now->copy()->startOfWeek()->format('Y-m-d');
            $endOfWeek = $now->copy()->endOfWeek()->format('Y-m-d');

            // Build the sort parameter as JSON
            $sort = json_encode([
                ['property' => 'points', 'direction' => 'DESC'],
                ['property' => 'goals', 'direction' => 'DESC'],
                ['property' => 'assists', 'direction' => 'DESC'],
            ]);

            // Build the cayenne expression for regular season games within the date range
            $cayenneExp = sprintf(
                'gameTypeId=2 and gameDate>="%s" and gameDate<="%s"',
                $startOfWeek,
                $endOfWeek
            );

            $response = Http::get(self::BASE_URL.'/skater/summary', [
                'isAggregate' => 'false',
                'isGame' => 'true',
                'sort' => $sort,
                'start' => 0,
                'limit' => $limit,
                'cayenneExp' => $cayenneExp,
            ]);

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json('data', []);

            return collect($data)->take($limit)->map(function ($player) {
                $fullName = $player['skaterFullName'] ?? '';
                $nameParts = explode(' ', $fullName);

                return [
                    'id' => $player['playerId'] ?? 0,
                    'first_name' => $nameParts[0] ?? '',
                    'last_name' => implode(' ', array_slice($nameParts, 1)) ?: ($nameParts[0] ?? ''),
                    'full_name' => $fullName,
                    'position' => $player['positionCode'] ?? '',
                    'sweater_number' => null, // Not available in this API endpoint
                    'team_name' => $this->getTeamFullName($player['teamAbbrev'] ?? ''),
                    'team_abbrev' => $player['teamAbbrev'] ?? '',
                    'points' => $player['points'] ?? 0,
                    'goals' => $player['goals'] ?? 0,
                    'assists' => $player['assists'] ?? 0,
                    'games_played' => $player['gamesPlayed'] ?? 0,
                    'headshot_url' => $this->getPlayerHeadshotUrl(
                        $player['playerId'] ?? 0,
                        $player['teamAbbrev'] ?? ''
                    ),
                ];
            })->toArray();
        });
    }

    /**
     * Get all NHL players for the current season.
     */
    public function getAllPlayers(int $limit = 1000, int $start = 0): array
    {
        $cacheKey = "nhl_all_players_{$start}_{$limit}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($limit, $start) {
            // Build the sort parameter as JSON
            $sort = json_encode([
                ['property' => 'lastName', 'direction' => 'ASC'],
                ['property' => 'firstName', 'direction' => 'ASC'],
            ]);

            // Build the cayenne expression for regular season games
            $cayenneExp = sprintf('seasonId=%s and gameTypeId=2', self::CURRENT_SEASON);

            $response = Http::get(self::BASE_URL.'/skater/summary', [
                'isAggregate' => 'false',
                'isGame' => 'false',
                'sort' => $sort,
                'start' => $start,
                'limit' => $limit,
                'cayenneExp' => $cayenneExp,
            ]);

            if (! $response->successful()) {
                return [
                    'data' => [],
                    'total' => 0,
                ];
            }

            $data = $response->json('data', []);
            $total = $response->json('total', 0);

            return [
                'data' => collect($data)->map(function ($player) {
                    $fullName = $player['skaterFullName'] ?? '';
                    $nameParts = explode(' ', $fullName);

                    return [
                        'id' => $player['playerId'] ?? 0,
                        'first_name' => $nameParts[0] ?? '',
                        'last_name' => implode(' ', array_slice($nameParts, 1)) ?: ($nameParts[0] ?? ''),
                        'full_name' => $fullName,
                        'position' => $player['positionCode'] ?? '',
                        'team_abbrev' => $player['teamAbbrevs'] ?? '',
                        'team_name' => $this->getTeamFullName($player['teamAbbrevs'] ?? ''),
                        'points' => $player['points'] ?? 0,
                        'goals' => $player['goals'] ?? 0,
                        'assists' => $player['assists'] ?? 0,
                        'games_played' => $player['gamesPlayed'] ?? 0,
                        'plus_minus' => $player['plusMinus'] ?? 0,
                        'penalty_minutes' => $player['penaltyMinutes'] ?? 0,
                        'shots' => $player['shots'] ?? 0,
                        'shooting_pct' => $player['shootingPct'] ?? 0,
                        'headshot_url' => $this->getPlayerHeadshotUrl(
                            $player['playerId'] ?? 0,
                            $player['teamAbbrevs'] ?? ''
                        ),
                    ];
                })->toArray(),
                'total' => $total,
            ];
        });
    }

    /**
     * Search players by name.
     */
    public function searchPlayers(string $query, int $limit = 50): array
    {
        $cacheKey = 'nhl_search_'.strtolower($query).'_'.$limit;

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($query, $limit) {
            // Build the cayenne expression to search by name
            $cayenneExp = sprintf(
                'seasonId=%s and gameTypeId=2 and skaterFullName likeIgnoreCase "%%%s%%"',
                self::CURRENT_SEASON,
                $query
            );

            // Build the sort parameter as JSON
            $sort = json_encode([
                ['property' => 'points', 'direction' => 'DESC'],
                ['property' => 'goals', 'direction' => 'DESC'],
            ]);

            $response = Http::get(self::BASE_URL.'/skater/summary', [
                'isAggregate' => 'false',
                'isGame' => 'false',
                'sort' => $sort,
                'start' => 0,
                'limit' => $limit,
                'cayenneExp' => $cayenneExp,
            ]);

            if (! $response->successful()) {
                return [
                    'data' => [],
                    'total' => 0,
                ];
            }

            $data = $response->json('data', []);
            $total = $response->json('total', 0);

            return [
                'data' => collect($data)->map(function ($player) {
                    $fullName = $player['skaterFullName'] ?? '';
                    $nameParts = explode(' ', $fullName);

                    return [
                        'id' => $player['playerId'] ?? 0,
                        'first_name' => $nameParts[0] ?? '',
                        'last_name' => implode(' ', array_slice($nameParts, 1)) ?: ($nameParts[0] ?? ''),
                        'full_name' => $fullName,
                        'position' => $player['positionCode'] ?? '',
                        'team_abbrev' => $player['teamAbbrevs'] ?? '',
                        'team_name' => $this->getTeamFullName($player['teamAbbrevs'] ?? ''),
                        'points' => $player['points'] ?? 0,
                        'goals' => $player['goals'] ?? 0,
                        'assists' => $player['assists'] ?? 0,
                        'games_played' => $player['gamesPlayed'] ?? 0,
                        'plus_minus' => $player['plusMinus'] ?? 0,
                        'penalty_minutes' => $player['penaltyMinutes'] ?? 0,
                        'shots' => $player['shots'] ?? 0,
                        'shooting_pct' => $player['shootingPct'] ?? 0,
                        'headshot_url' => $this->getPlayerHeadshotUrl(
                            $player['playerId'] ?? 0,
                            $player['teamAbbrevs'] ?? ''
                        ),
                    ];
                })->toArray(),
                'total' => $total,
            ];
        });
    }

    /**
     * Get team full name from abbreviation.
     */
    private function getTeamFullName(string $abbrev): string
    {
        $teams = [
            'ANA' => 'Anaheim Ducks',
            'BOS' => 'Boston Bruins',
            'BUF' => 'Buffalo Sabres',
            'CAR' => 'Carolina Hurricanes',
            'CBJ' => 'Columbus Blue Jackets',
            'CGY' => 'Calgary Flames',
            'CHI' => 'Chicago Blackhawks',
            'COL' => 'Colorado Avalanche',
            'DAL' => 'Dallas Stars',
            'DET' => 'Detroit Red Wings',
            'EDM' => 'Edmonton Oilers',
            'FLA' => 'Florida Panthers',
            'LAK' => 'Los Angeles Kings',
            'MIN' => 'Minnesota Wild',
            'MTL' => 'Montreal Canadiens',
            'NJD' => 'New Jersey Devils',
            'NSH' => 'Nashville Predators',
            'NYI' => 'New York Islanders',
            'NYR' => 'New York Rangers',
            'OTT' => 'Ottawa Senators',
            'PHI' => 'Philadelphia Flyers',
            'PIT' => 'Pittsburgh Penguins',
            'SEA' => 'Seattle Kraken',
            'SJS' => 'San Jose Sharks',
            'STL' => 'St. Louis Blues',
            'TBL' => 'Tampa Bay Lightning',
            'TOR' => 'Toronto Maple Leafs',
            'UTA' => 'Utah Hockey Club',
            'VAN' => 'Vancouver Canucks',
            'VGK' => 'Vegas Golden Knights',
            'WPG' => 'Winnipeg Jets',
            'WSH' => 'Washington Capitals',
        ];

        return $teams[$abbrev] ?? $abbrev;
    }

    /**
     * Get team schedule and count games in date range.
     */
    public function getTeamGamesInDateRange(string $teamAbbrev, string $startDate, string $endDate): int
    {
        $cacheKey = "nhl_team_games_{$teamAbbrev}_{$startDate}_{$endDate}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($teamAbbrev, $startDate, $endDate) {
            // Get team ID from abbreviation
            $teamId = $this->getTeamIdFromAbbrev($teamAbbrev);

            if (! $teamId) {
                return 0;
            }

            // Fetch schedule from NHL API
            $response = Http::get("https://api-web.nhle.com/v1/club-schedule-season/{$teamAbbrev}/now");

            if (! $response->successful()) {
                return 0;
            }

            $games = $response->json('games', []);

            // Filter games within date range
            $count = collect($games)->filter(function ($game) use ($startDate, $endDate) {
                $gameDate = $game['gameDate'] ?? null;

                if (! $gameDate) {
                    return false;
                }

                // Extract just the date part (YYYY-MM-DD)
                $gameDateOnly = substr($gameDate, 0, 10);

                return $gameDateOnly >= $startDate && $gameDateOnly <= $endDate;
            })->count();

            return $count;
        });
    }

    /**
     * Get team ID from abbreviation.
     */
    private function getTeamIdFromAbbrev(string $abbrev): ?int
    {
        $teams = [
            'ANA' => 24, 'BOS' => 6, 'BUF' => 7, 'CAR' => 12, 'CBJ' => 29,
            'CGY' => 20, 'CHI' => 16, 'COL' => 21, 'DAL' => 25, 'DET' => 17,
            'EDM' => 22, 'FLA' => 13, 'LAK' => 26, 'MIN' => 30, 'MTL' => 8,
            'NJD' => 1, 'NSH' => 18, 'NYI' => 2, 'NYR' => 3, 'OTT' => 9,
            'PHI' => 4, 'PIT' => 5, 'SEA' => 55, 'SJS' => 28, 'STL' => 19,
            'TBL' => 14, 'TOR' => 10, 'UTA' => 53, 'VAN' => 23, 'VGK' => 54,
            'WPG' => 52, 'WSH' => 15,
        ];

        return $teams[$abbrev] ?? null;
    }

    /**
     * Get player stats for a specific date range (for pool duration).
     */
    public function getPlayerStatsInDateRange(int $playerId, string $startDate, string $endDate): array
    {
        $cacheKey = "nhl_player_stats_{$playerId}_{$startDate}_{$endDate}";

        return Cache::remember($cacheKey, now()->addMinute(), function () use ($playerId, $startDate, $endDate) {
            $cayenneExp = sprintf(
                'seasonId=%s and gameTypeId=2 and playerId=%d and gameDate>="%s" and gameDate<="%s"',
                self::CURRENT_SEASON,
                $playerId,
                $startDate,
                $endDate
            );

            $response = Http::get(self::BASE_URL.'/skater/summary', [
                'isAggregate' => 'false',
                'isGame' => 'true',
                'cayenneExp' => $cayenneExp,
                'limit' => 100,
            ]);

            if (! $response->successful()) {
                return [
                    'goals' => 0,
                    'assists' => 0,
                    'points' => 0,
                    'games_played' => 0,
                    'plus_minus' => 0,
                ];
            }

            $data = $response->json('data', []);

            if (empty($data)) {
                return [
                    'goals' => 0,
                    'assists' => 0,
                    'points' => 0,
                    'games_played' => 0,
                    'plus_minus' => 0,
                ];
            }

            // Aggregate stats from all games in the date range
            $totalGoals = 0;
            $totalAssists = 0;
            $totalPoints = 0;
            $totalPlusMinus = 0;
            $gamesPlayed = count($data);

            foreach ($data as $game) {
                $totalGoals += $game['goals'] ?? 0;
                $totalAssists += $game['assists'] ?? 0;
                $totalPoints += $game['points'] ?? 0;
                $totalPlusMinus += $game['plusMinus'] ?? 0;
            }

            return [
                'goals' => $totalGoals,
                'assists' => $totalAssists,
                'points' => $totalPoints,
                'games_played' => $gamesPlayed,
                'plus_minus' => $totalPlusMinus,
            ];
        });
    }

    /**
     * Get player headshot URL.
     */
    private function getPlayerHeadshotUrl(int $playerId, string $teamAbbrev): string
    {
        // Primary URL pattern for current season
        if ($teamAbbrev) {
            return sprintf(
                'https://assets.nhle.com/mugs/nhl/%s/%s/%d.png',
                self::CURRENT_SEASON,
                $teamAbbrev,
                $playerId
            );
        }

        // Fallback URL pattern
        return sprintf(
            'https://nhl.bamcontent.com/images/headshots/current/168x168/%d.png',
            $playerId
        );
    }
}
