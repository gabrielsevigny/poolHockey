<?php

namespace Tests\Feature;

use App\Models\Pool;
use App\Models\PoolPlayer;
use App\Models\RuleSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoolPositionLimitsValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_add_more_players_than_position_max_limit(): void
    {
        $user = User::factory()->create();

        // Create a rule setting with position limits
        $ruleSetting = RuleSetting::create([
            'name' => 'Test Rules with Position Limits',
            'template_type' => 'custom',
            'is_default' => false,
            'rules' => [
                'scoring_rules' => [
                    ['type' => 'goal', 'label' => 'But', 'points' => 1],
                ],
                'player_limits' => [
                    'max_per_user' => 10,
                    'by_position' => [
                        'C' => ['min' => 1, 'max' => 2],
                        'G' => ['min' => 1, 'max' => 1],
                    ],
                ],
            ],
        ]);

        $pool = Pool::create([
            'name' => 'Test Pool',
            'start_date' => now()->addWeek(),
            'end_date' => now()->addWeeks(2),
            'rule_setting_id' => $ruleSetting->id,
            'owner_id' => $user->id,
            'status' => 'selection',
        ]);

        $pool->users()->attach($user->id);

        // Add 2 centers (max allowed)
        PoolPlayer::create([
            'pool_id' => $pool->id,
            'user_id' => $user->id,
            'nhl_player_id' => 8478402,
            'player_name' => 'Connor McDavid',
            'position' => 'C',
            'team_abbrev' => 'EDM',
            'team_name' => 'Edmonton Oilers',
            'headshot_url' => 'https://example.com/image.jpg',
            'draft_order' => 1,
        ]);

        PoolPlayer::create([
            'pool_id' => $pool->id,
            'user_id' => $user->id,
            'nhl_player_id' => 8477934,
            'player_name' => 'Auston Matthews',
            'position' => 'C',
            'team_abbrev' => 'TOR',
            'team_name' => 'Toronto Maple Leafs',
            'headshot_url' => 'https://example.com/image.jpg',
            'draft_order' => 2,
        ]);

        // Try to add a 3rd center (should fail)
        $response = $this->actingAs($user)->postJson(route('pools.players.store', $pool), [
            'nhl_player_id' => 8479318,
            'player_name' => 'Nathan MacKinnon',
            'position' => 'C',
            'team_abbrev' => 'COL',
            'team_name' => 'Colorado Avalanche',
            'headshot_url' => 'https://example.com/image.jpg',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Vous avez atteint la limite maximale de 2 joueur(s) pour la position C.',
        ]);

        // Verify player count hasn't changed
        $this->assertEquals(2, PoolPlayer::where('pool_id', $pool->id)
            ->where('user_id', $user->id)
            ->where('position', 'C')
            ->count());
    }

    public function test_user_can_add_player_within_position_limits(): void
    {
        $user = User::factory()->create();

        $ruleSetting = RuleSetting::create([
            'name' => 'Test Rules',
            'template_type' => 'custom',
            'is_default' => false,
            'rules' => [
                'scoring_rules' => [
                    ['type' => 'goal', 'label' => 'But', 'points' => 1],
                ],
                'player_limits' => [
                    'max_per_user' => 10,
                    'by_position' => [
                        'D' => ['min' => 2, 'max' => 4],
                    ],
                ],
            ],
        ]);

        $pool = Pool::create([
            'name' => 'Test Pool',
            'start_date' => now()->addWeek(),
            'end_date' => now()->addWeeks(2),
            'rule_setting_id' => $ruleSetting->id,
            'owner_id' => $user->id,
            'status' => 'selection',
        ]);

        $pool->users()->attach($user->id);

        // Add 1st defenseman (within limits)
        $response = $this->actingAs($user)->postJson(route('pools.players.store', $pool), [
            'nhl_player_id' => 8478550,
            'player_name' => 'Cale Makar',
            'position' => 'D',
            'team_abbrev' => 'COL',
            'team_name' => 'Colorado Avalanche',
            'headshot_url' => 'https://example.com/image.jpg',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Joueur ajouté avec succès.',
        ]);

        $this->assertEquals(1, PoolPlayer::where('pool_id', $pool->id)
            ->where('user_id', $user->id)
            ->where('position', 'D')
            ->count());
    }

    public function test_user_can_add_players_without_position_limits(): void
    {
        $user = User::factory()->create();

        $ruleSetting = RuleSetting::create([
            'name' => 'Test Rules No Limits',
            'template_type' => 'custom',
            'is_default' => false,
            'rules' => [
                'scoring_rules' => [
                    ['type' => 'goal', 'label' => 'But', 'points' => 1],
                ],
                'player_limits' => [
                    'max_per_user' => 20,
                ],
            ],
        ]);

        $pool = Pool::create([
            'name' => 'Test Pool',
            'start_date' => now()->addWeek(),
            'end_date' => now()->addWeeks(2),
            'rule_setting_id' => $ruleSetting->id,
            'owner_id' => $user->id,
            'status' => 'selection',
        ]);

        $pool->users()->attach($user->id);

        // Add multiple players of same position (no limits)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->actingAs($user)->postJson(route('pools.players.store', $pool), [
                'nhl_player_id' => 8478400 + $i,
                'player_name' => "Player {$i}",
                'position' => 'L',
                'team_abbrev' => 'MTL',
                'team_name' => 'Montreal Canadiens',
                'headshot_url' => 'https://example.com/image.jpg',
            ]);

            $response->assertStatus(200);
        }

        $this->assertEquals(5, PoolPlayer::where('pool_id', $pool->id)
            ->where('user_id', $user->id)
            ->where('position', 'L')
            ->count());
    }
}
