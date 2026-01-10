<?php

namespace Tests\Unit;

use App\Services\NHLApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NHLApiServiceTest extends TestCase
{
    public function test_get_top_scorers_returns_array(): void
    {
        Cache::flush();

        Http::fake([
            'api.nhle.com/stats/rest/en/skater/summary*' => Http::response([
                'data' => [
                    [
                        'playerId' => 8478402,
                        'skaterFullName' => 'Connor McDavid',
                        'positionCode' => 'C',
                        'sweaterNumber' => 97,
                        'teamName' => 'Edmonton Oilers',
                        'teamAbbrev' => 'EDM',
                        'points' => 50,
                        'goals' => 20,
                        'assists' => 30,
                        'gamesPlayed' => 5,
                    ],
                ],
                'total' => 1,
            ], 200),
        ]);

        $service = new NHLApiService;
        $scorers = $service->getTopScorers(5);

        $this->assertIsArray($scorers);
        $this->assertNotEmpty($scorers);

        $firstScorer = $scorers[0];
        $this->assertArrayHasKey('id', $firstScorer);
        $this->assertArrayHasKey('full_name', $firstScorer);
        $this->assertArrayHasKey('points', $firstScorer);
        $this->assertArrayHasKey('goals', $firstScorer);
        $this->assertArrayHasKey('assists', $firstScorer);
        $this->assertArrayHasKey('headshot_url', $firstScorer);
    }

    public function test_get_top_scorers_uses_cache(): void
    {
        Cache::flush();

        Http::fake([
            'api.nhle.com/stats/rest/en/skater/summary*' => Http::response([
                'data' => [
                    [
                        'playerId' => 8478402,
                        'skaterFullName' => 'Connor McDavid',
                        'positionCode' => 'C',
                        'sweaterNumber' => 97,
                        'teamName' => 'Edmonton Oilers',
                        'teamAbbrev' => 'EDM',
                        'points' => 50,
                        'goals' => 20,
                        'assists' => 30,
                        'gamesPlayed' => 5,
                    ],
                ],
                'total' => 1,
            ], 200),
        ]);

        $service = new NHLApiService;

        // First call should hit the API
        $service->getTopScorers(5);
        Http::assertSentCount(1);

        // Second call should use cache
        $service->getTopScorers(5);
        Http::assertSentCount(1);
    }

    public function test_get_top_scorers_handles_api_failure(): void
    {
        Cache::flush();

        Http::fake([
            'api.nhle.com/stats/rest/en/skater/summary*' => Http::response([], 500),
        ]);

        $service = new NHLApiService;
        $scorers = $service->getTopScorers(5);

        $this->assertIsArray($scorers);
        $this->assertEmpty($scorers);
    }

    public function test_get_all_players_returns_array_with_data_and_total(): void
    {
        Cache::flush();

        Http::fake([
            'api.nhle.com/stats/rest/en/skater/summary*' => Http::response([
                'data' => [
                    [
                        'playerId' => 8478402,
                        'skaterFullName' => 'Connor McDavid',
                        'positionCode' => 'C',
                        'teamAbbrevs' => 'EDM',
                        'points' => 100,
                        'goals' => 40,
                        'assists' => 60,
                        'gamesPlayed' => 70,
                        'plusMinus' => 15,
                        'penaltyMinutes' => 20,
                        'shots' => 250,
                        'shootingPct' => 0.16,
                    ],
                    [
                        'playerId' => 8477934,
                        'skaterFullName' => 'Leon Draisaitl',
                        'positionCode' => 'C',
                        'teamAbbrevs' => 'EDM',
                        'points' => 95,
                        'goals' => 45,
                        'assists' => 50,
                        'gamesPlayed' => 70,
                        'plusMinus' => 18,
                        'penaltyMinutes' => 25,
                        'shots' => 270,
                        'shootingPct' => 0.167,
                    ],
                ],
                'total' => 920,
            ], 200),
        ]);

        $service = new NHLApiService;
        $result = $service->getAllPlayers(10);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(920, $result['total']);
        $this->assertCount(2, $result['data']);

        $firstPlayer = $result['data'][0];
        $this->assertArrayHasKey('id', $firstPlayer);
        $this->assertArrayHasKey('full_name', $firstPlayer);
        $this->assertArrayHasKey('points', $firstPlayer);
        $this->assertArrayHasKey('team_name', $firstPlayer);
    }

    public function test_get_all_players_uses_cache(): void
    {
        Cache::flush();

        Http::fake([
            'api.nhle.com/stats/rest/en/skater/summary*' => Http::response([
                'data' => [
                    [
                        'playerId' => 8478402,
                        'skaterFullName' => 'Connor McDavid',
                        'positionCode' => 'C',
                        'teamAbbrevs' => 'EDM',
                        'points' => 100,
                        'goals' => 40,
                        'assists' => 60,
                        'gamesPlayed' => 70,
                        'plusMinus' => 15,
                        'penaltyMinutes' => 20,
                        'shots' => 250,
                        'shootingPct' => 0.16,
                    ],
                ],
                'total' => 920,
            ], 200),
        ]);

        $service = new NHLApiService;

        // First call should hit the API
        $service->getAllPlayers(10);
        Http::assertSentCount(1);

        // Second call should use cache
        $service->getAllPlayers(10);
        Http::assertSentCount(1);
    }

    public function test_search_players_filters_by_name(): void
    {
        Cache::flush();

        Http::fake([
            'api.nhle.com/stats/rest/en/skater/summary*' => Http::response([
                'data' => [
                    [
                        'playerId' => 8478402,
                        'skaterFullName' => 'Connor McDavid',
                        'positionCode' => 'C',
                        'teamAbbrevs' => 'EDM',
                        'points' => 100,
                        'goals' => 40,
                        'assists' => 60,
                        'gamesPlayed' => 70,
                        'plusMinus' => 15,
                        'penaltyMinutes' => 20,
                        'shots' => 250,
                        'shootingPct' => 0.16,
                    ],
                    [
                        'playerId' => 8477934,
                        'skaterFullName' => 'Leon Draisaitl',
                        'positionCode' => 'C',
                        'teamAbbrevs' => 'EDM',
                        'points' => 95,
                        'goals' => 45,
                        'assists' => 50,
                        'gamesPlayed' => 70,
                        'plusMinus' => 18,
                        'penaltyMinutes' => 25,
                        'shots' => 270,
                        'shootingPct' => 0.167,
                    ],
                ],
                'total' => 2,
            ], 200),
        ]);

        $service = new NHLApiService;
        $result = $service->searchPlayers('McDavid');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertCount(1, $result['data']);
        $this->assertEquals('Connor McDavid', $result['data'][0]['full_name']);
    }
}
