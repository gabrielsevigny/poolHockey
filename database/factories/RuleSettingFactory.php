<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RuleSetting>
 */
class RuleSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'template_type' => 'custom',
            'is_default' => false,
            'rules' => [
                'scoring_rules' => [
                    ['type' => 'goal', 'label' => 'But', 'points' => 1],
                    ['type' => 'assist', 'label' => 'Passe', 'points' => 1],
                ],
                'player_limits' => [
                    'max_per_user' => 20,
                    'by_position' => [],
                ],
            ],
        ];
    }
}
