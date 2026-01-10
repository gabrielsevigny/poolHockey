<?php

namespace Database\Seeders;

use App\Models\RuleSetting;
use Illuminate\Database\Seeder;

class RuleSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Template 1: Règles Standard (NHL classique)
        RuleSetting::updateOrCreate(
            ['name' => 'Règles Standard'],
            [
                'name' => 'Règles Standard',
                'template_type' => 'standard',
                'is_default' => true,
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
            ]
        );

        // Template 2: Règles avec Gardiens
        RuleSetting::updateOrCreate(
            ['name' => 'Règles avec Gardiens'],
            [
                'name' => 'Règles avec Gardiens',
                'template_type' => 'with_goalies',
                'is_default' => false,
                'rules' => [
                    'scoring_rules' => [
                        ['type' => 'goal', 'label' => 'But', 'points' => 1],
                        ['type' => 'assist', 'label' => 'Passe', 'points' => 1],
                        ['type' => 'shutout', 'label' => 'Blanchissage', 'points' => 3],
                        ['type' => 'victory', 'label' => 'Victoire', 'points' => 2],
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
                ],
            ]
        );

        // Template 3: Règles Avancées
        RuleSetting::updateOrCreate(
            ['name' => 'Règles Avancées'],
            [
                'name' => 'Règles Avancées',
                'template_type' => 'advanced',
                'is_default' => false,
                'rules' => [
                    'scoring_rules' => [
                        ['type' => 'goal', 'label' => 'But', 'points' => 2],
                        ['type' => 'assist', 'label' => 'Passe', 'points' => 1],
                        ['type' => 'power_play_goal', 'label' => 'But en supériorité', 'points' => 3],
                        ['type' => 'short_handed_goal', 'label' => 'But en infériorité', 'points' => 4],
                        ['type' => 'shutout', 'label' => 'Blanchissage', 'points' => 5],
                        ['type' => 'victory', 'label' => 'Victoire', 'points' => 2],
                    ],
                    'player_limits' => [
                        'max_per_user' => 18,
                        'by_position' => [
                            'C' => 3,
                            'L' => 3,
                            'R' => 3,
                            'D' => 6,
                            'G' => 2,
                        ],
                    ],
                ],
            ]
        );

        $this->command->info('Rule settings created/updated successfully');
    }
}
