<?php

namespace Database\Factories;

use App\Models\RuleSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pool>
 */
class PoolFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true).' Pool',
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(8),
            'draft_start_date' => now(),
            'draft_end_date' => now()->addDays(1),
            'rule_setting_id' => RuleSetting::factory(),
            'owner_id' => User::factory(),
            'status' => 'selection',
        ];
    }
}
