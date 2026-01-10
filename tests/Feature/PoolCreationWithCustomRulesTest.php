<?php

namespace Tests\Feature;

use App\Models\Pool;
use App\Models\RuleSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoolCreationWithCustomRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_pool_can_be_created_with_existing_rules(): void
    {
        $user = User::factory()->create();
        $ruleSetting = RuleSetting::factory()->create();
        $participants = User::factory()->count(2)->create();

        $response = $this->actingAs($user)->post('/pools', [
            'name' => 'Test Pool',
            'start_date' => now()->addDays(1)->format('Y-m-d'),
            'end_date' => now()->addDays(8)->format('Y-m-d'),
            'rule_setting_id' => $ruleSetting->id,
            'user_ids' => $participants->pluck('id')->toArray(),
        ]);

        $response->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('pools', [
            'name' => 'Test Pool',
            'rule_setting_id' => $ruleSetting->id,
            'owner_id' => $user->id,
        ]);
    }

    public function test_pool_can_be_created_with_custom_rules(): void
    {
        $user = User::factory()->create();
        $participants = User::factory()->count(2)->create();

        $customRules = [
            'scoring_rules' => [
                ['type' => 'goal', 'label' => 'But', 'points' => 2],
                ['type' => 'assist', 'label' => 'Passe', 'points' => 1],
                ['type' => 'shutout', 'label' => 'Blanchissage', 'points' => 5],
            ],
            'player_limits' => [
                'max_per_user' => 15,
                'by_position' => [
                    'C' => 2,
                    'L' => 2,
                    'R' => 2,
                    'D' => 4,
                    'G' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($user)->post('/pools', [
            'name' => 'Custom Pool',
            'start_date' => now()->addDays(1)->format('Y-m-d'),
            'end_date' => now()->addDays(8)->format('Y-m-d'),
            'custom_rules' => $customRules,
            'user_ids' => $participants->pluck('id')->toArray(),
        ]);

        $response->assertRedirect(route('dashboard'));

        // Verify the pool was created
        $pool = Pool::where('name', 'Custom Pool')->first();
        $this->assertNotNull($pool);

        // Verify a new rule setting was created with custom rules
        $this->assertNotNull($pool->ruleSetting);
        $this->assertEquals('custom', $pool->ruleSetting->template_type);
        $this->assertNotNull($pool->ruleSetting->rules);
        $this->assertEquals(3, count($pool->ruleSetting->rules['scoring_rules']));
        $this->assertEquals(2, $pool->ruleSetting->rules['scoring_rules'][0]['points']);
        $this->assertEquals(15, $pool->ruleSetting->rules['player_limits']['max_per_user']);
    }

    public function test_pool_creation_requires_either_rule_setting_or_custom_rules(): void
    {
        $user = User::factory()->create();
        $participants = User::factory()->count(2)->create();

        $response = $this->actingAs($user)->post('/pools', [
            'name' => 'Test Pool',
            'start_date' => now()->addDays(1)->format('Y-m-d'),
            'end_date' => now()->addDays(8)->format('Y-m-d'),
            'user_ids' => $participants->pluck('id')->toArray(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors();

        // Should show the custom error we defined
        $this->assertEquals(
            'Veuillez sélectionner un règlement ou créer des règles personnalisées.',
            session('errors')->get('rule_setting_id')[0] ?? null
        );
    }

    public function test_custom_rules_validation_requires_scoring_rules(): void
    {
        $user = User::factory()->create();
        $participants = User::factory()->count(2)->create();

        $invalidCustomRules = [
            'player_limits' => [
                'max_per_user' => 15,
                'by_position' => [],
            ],
        ];

        $response = $this->actingAs($user)->post('/pools', [
            'name' => 'Custom Pool',
            'start_date' => now()->addDays(1)->format('Y-m-d'),
            'end_date' => now()->addDays(8)->format('Y-m-d'),
            'custom_rules' => $invalidCustomRules,
            'user_ids' => $participants->pluck('id')->toArray(),
        ]);

        $response->assertSessionHasErrors('custom_rules.scoring_rules');
    }

    public function test_custom_rules_validation_requires_valid_player_limits(): void
    {
        $user = User::factory()->create();
        $participants = User::factory()->count(2)->create();

        $invalidCustomRules = [
            'scoring_rules' => [
                ['type' => 'goal', 'label' => 'But', 'points' => 1],
            ],
            'player_limits' => [
                'max_per_user' => 100, // Too high (max is 50)
                'by_position' => [],
            ],
        ];

        $response = $this->actingAs($user)->post('/pools', [
            'name' => 'Custom Pool',
            'start_date' => now()->addDays(1)->format('Y-m-d'),
            'end_date' => now()->addDays(8)->format('Y-m-d'),
            'custom_rules' => $invalidCustomRules,
            'user_ids' => $participants->pluck('id')->toArray(),
        ]);

        $response->assertSessionHasErrors('custom_rules.player_limits.max_per_user');
    }
}
