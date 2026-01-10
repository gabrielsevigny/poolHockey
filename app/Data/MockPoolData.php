<?php

namespace App\Data;

class MockPoolData
{
    /**
     * Get default mock players for testing
     */
    public static function getDefaultPlayers(): array
    {
        return [
            [
                'team' => 'Carolina Hurricanes',
                'name' => 'Sebastian Aho',
                'position' => 'C',
                'goals' => 4,
                'assists' => 0,
                'shutouts' => 0,
                'victories' => 0,
                'defeats' => 0,
                'points' => 8,
            ],
            [
                'team' => 'Edmonton Oilers',
                'name' => 'Connor McDavid',
                'position' => 'C',
                'goals' => 5,
                'assists' => 3,
                'shutouts' => 0,
                'victories' => 0,
                'defeats' => 0,
                'points' => 13,
            ],
            [
                'team' => 'Edmonton Oilers',
                'name' => 'Leon Draisaitl',
                'position' => 'C',
                'goals' => 2,
                'assists' => 6,
                'shutouts' => 0,
                'victories' => 0,
                'defeats' => 0,
                'points' => 10,
            ],
            [
                'team' => 'Dallas Stars',
                'name' => 'Mikko Rantanen',
                'position' => 'AD',
                'goals' => 3,
                'assists' => 7,
                'shutouts' => 0,
                'victories' => 0,
                'defeats' => 0,
                'points' => 13,
            ],
            [
                'team' => 'Washington Capitals',
                'name' => 'Alex Ovechkin',
                'position' => 'AG',
                'goals' => 3,
                'assists' => 4,
                'shutouts' => 0,
                'victories' => 0,
                'defeats' => 0,
                'points' => 10,
            ],
            [
                'team' => 'Minnesota Wild',
                'name' => 'Marcus Johansson',
                'position' => 'C',
                'goals' => 0,
                'assists' => 1,
                'shutouts' => 0,
                'victories' => 0,
                'defeats' => 0,
                'points' => 1,
            ],
            [
                'team' => 'Tampa Bay Lightning',
                'name' => 'Nikita Kucherov',
                'position' => 'AD',
                'goals' => 2,
                'assists' => 7,
                'shutouts' => 0,
                'victories' => 0,
                'defeats' => 0,
                'points' => 11,
            ],
            [
                'team' => 'Montreal Canadiens',
                'name' => 'Cole Caufield',
                'position' => 'AD',
                'goals' => 1,
                'assists' => 6,
                'shutouts' => 0,
                'victories' => 0,
                'defeats' => 0,
                'points' => 8,
            ],
            [
                'team' => 'Colorado Avalanche',
                'name' => 'Artturi Lehkonen',
                'position' => 'AG',
                'goals' => 2,
                'assists' => 4,
                'shutouts' => 0,
                'victories' => 0,
                'defeats' => 0,
                'points' => 8,
            ],
            [
                'team' => 'Winnipeg Jets',
                'name' => 'Connor Hellebuyck',
                'position' => 'G',
                'goals' => 0,
                'assists' => 0,
                'shutouts' => 0,
                'victories' => 2,
                'defeats' => 0,
                'points' => 4,
            ],
        ];
    }

    /**
     * Get all available pools with their dates and statuses
     */
    public static function getPoolsConfig(): array
    {
        return [
            'm5gr84b9' => ['date' => '6 décembre', 'status' => 'finished'],
            'm5gr84i9' => ['date' => '1 décembre', 'status' => 'finished'],
            'm5gr84i1' => ['date' => '29 novembre', 'status' => 'finished'],
            'm5gr84i2' => ['date' => '22 novembre', 'status' => 'finished'],
            'm5gr84i3' => ['date' => '15 novembre', 'status' => 'active'],
            'm5gr84i4' => ['date' => '8 novembre', 'status' => 'finished'],
            'm5gr84i5' => ['date' => '1 novembre', 'status' => 'finished'],
            'm5gr84i6' => ['date' => '25 octobre', 'status' => 'active'],
            'm5gr84i7' => ['date' => '18 octobre', 'status' => 'finished'],
            'm5gr84i8' => ['date' => '11 octobre', 'status' => 'finished'],
            'm5gr84i10' => ['date' => '4 octobre', 'status' => 'upcoming'],
            'm5gr84i11' => ['date' => '27 septembre', 'status' => 'finished'],
            'm5gr84i12' => ['date' => '20 septembre', 'status' => 'finished'],
            'm5gr84i13' => ['date' => '13 septembre', 'status' => 'selection'],
            'm5gr84i14' => ['date' => '6 septembre', 'status' => 'finished'],
            'm5gr84i15' => ['date' => '30 août', 'status' => 'finished'],
            'm5gr84i16' => ['date' => '23 août', 'status' => 'upcoming'],
            'm5gr84i17' => ['date' => '16 août', 'status' => 'finished'],
            'm5gr84i18' => ['date' => '9 août', 'status' => 'finished'],
            'm5gr84i19' => ['date' => '2 août', 'status' => 'active'],
            'm5gr84i20' => ['date' => '26 juillet', 'status' => 'finished'],
            'm5gr84i21' => ['date' => '19 juillet', 'status' => 'finished'],
            'm5gr84i22' => ['date' => '12 juillet', 'status' => 'selection'],
            'm5gr84i23' => ['date' => '5 juillet', 'status' => 'finished'],
            'm5gr84i24' => ['date' => '28 juin', 'status' => 'finished'],
            'm5gr84i25' => ['date' => '21 juin', 'status' => 'active'],
            'm5gr84i26' => ['date' => '14 juin', 'status' => 'finished'],
            'm5gr84i27' => ['date' => '7 juin', 'status' => 'finished'],
            'm5gr84i28' => ['date' => '31 mai', 'status' => 'upcoming'],
            'm5gr84i29' => ['date' => '24 mai', 'status' => 'finished'],
        ];
    }

    /**
     * Get a single pool by ID
     */
    public static function getPoolById(string $id): ?array
    {
        $poolsConfig = self::getPoolsConfig();

        if (! isset($poolsConfig[$id])) {
            return null;
        }

        $poolData = $poolsConfig[$id];
        $players = self::getDefaultPlayers();

        return [
            'id' => $id,
            'date' => $poolData['date'],
            'status' => $poolData['status'],
            'players' => $players,
            'totalGoals' => array_sum(array_column($players, 'goals')),
            'totalAssists' => array_sum(array_column($players, 'assists')),
            'totalShutouts' => array_sum(array_column($players, 'shutouts')),
            'totalVictories' => array_sum(array_column($players, 'victories')),
            'totalDefeats' => array_sum(array_column($players, 'defeats')),
            'totalOvertime' => 0, // Mock value - will be calculated from real data later
            'totalPoints' => array_sum(array_column($players, 'points')),
        ];
    }

    /**
     * Get all pools
     */
    public static function getAllPools(): array
    {
        $poolsConfig = self::getPoolsConfig();
        $pools = [];

        foreach ($poolsConfig as $id => $data) {
            $pool = self::getPoolById($id);
            if ($pool) {
                $pools[] = $pool;
            }
        }

        return $pools;
    }
}
