<?php

namespace Tests\Feature;

use App\Models\Pool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoolCreationWithPositionLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pool_can_be_created_with_custom_position_limits(): void
    {
        $user = User::factory()->create();
        $participants = User::factory()->count(3)->create();

        $startDate = now()->addWeek()->format('Y-m-d');
        $endDate = now()->addWeeks(2)->format('Y-m-d');

        $response = $this->actingAs($user)->post(route('pools.store'), [
            'name' => 'Pool avec limites par position',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'custom_rules' => [
                'scoring_rules' => [
                    ['type' => 'goal', 'label' => 'But', 'points' => 1],
                    ['type' => 'assist', 'label' => 'Passe', 'points' => 1],
                ],
                'player_limits' => [
                    'max_per_user' => 15,
                    'by_position' => [
                        'C' => ['min' => 1, 'max' => 2],
                        'L' => ['min' => 1, 'max' => 2],
                        'R' => ['min' => 1, 'max' => 2],
                        'D' => ['min' => 2, 'max' => 4],
                        'G' => ['min' => 1, 'max' => 1],
                    ],
                ],
            ],
            'user_ids' => $participants->pluck('id')->toArray(),
        ]);

        $response->assertRedirect(route('dashboard'));

        // Verify pool was created
        $pool = Pool::latest()->first();
        $this->assertNotNull($pool);
        $this->assertEquals('Pool avec limites par position', $pool->name);

        // Verify rule setting was created with custom rules
        $ruleSetting = $pool->ruleSetting;
        $this->assertNotNull($ruleSetting);
        $this->assertTrue($ruleSetting->usesDynamicRules());

        // Verify player limits
        $playerLimits = $ruleSetting->getPlayerLimits();
        $this->assertEquals(15, $playerLimits['max_per_user']);

        // Verify position limits
        $this->assertArrayHasKey('by_position', $playerLimits);
        $this->assertArrayHasKey('C', $playerLimits['by_position']);
        $this->assertEquals(1, $playerLimits['by_position']['C']['min']);
        $this->assertEquals(2, $playerLimits['by_position']['C']['max']);

        $this->assertArrayHasKey('G', $playerLimits['by_position']);
        $this->assertEquals(1, $playerLimits['by_position']['G']['min']);
        $this->assertEquals(1, $playerLimits['by_position']['G']['max']);

        // Verify participants were attached
        $this->assertEquals(3, $pool->users()->count());
    }

    public function test_pool_can_be_created_without_position_limits(): void
    {
        $user = User::factory()->create();
        $participants = User::factory()->count(2)->create();

        $startDate = now()->addWeek()->format('Y-m-d');
        $endDate = now()->addWeeks(2)->format('Y-m-d');

        $response = $this->actingAs($user)->post(route('pools.store'), [
            'name' => 'Pool sans limites par position',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'custom_rules' => [
                'scoring_rules' => [
                    ['type' => 'goal', 'label' => 'But', 'points' => 2],
                    ['type' => 'assist', 'label' => 'Passe', 'points' => 1],
                ],
                'player_limits' => [
                    'max_per_user' => 20,
                ],
            ],
            'user_ids' => $participants->pluck('id')->toArray(),
        ]);

        $response->assertRedirect(route('dashboard'));

        $pool = Pool::latest()->first();
        $this->assertNotNull($pool);

        $playerLimits = $pool->ruleSetting->getPlayerLimits();
        $this->assertEquals(20, $playerLimits['max_per_user']);

        // Should have empty by_position or not set
        $this->assertTrue(
            ! isset($playerLimits['by_position']) ||
            empty($playerLimits['by_position'])
        );
    }
}
