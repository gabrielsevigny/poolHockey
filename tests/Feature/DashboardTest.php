<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);
    }

    public function test_dashboard_includes_top_scorers_data()
    {
        Cache::flush();

        Http::fake([
            'api.nhle.com/stats/rest/en/skater/summary*' => Http::response([
                'data' => [
                    [
                        'playerId' => 8478402,
                        'skaterFullName' => 'Connor McDavid',
                        'positionCode' => 'C',
                        'teamAbbrev' => 'EDM',
                        'points' => 50,
                        'goals' => 20,
                        'assists' => 30,
                        'gamesPlayed' => 35,
                    ],
                ],
                'total' => 1,
            ], 200),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard/Index')
            ->has('topScorers')
            ->where('topScorers.0.full_name', 'Connor McDavid')
            ->where('topScorers.0.points', 50)
        );
    }

    public function test_dashboard_includes_pools_data()
    {
        Cache::flush();

        Http::fake([
            'api.nhle.com/stats/rest/en/leaders/skaters/points*' => Http::response([
                'data' => [],
                'total' => 0,
            ], 200),
        ]);

        $user = User::factory()->create();

        // Create a rule setting and pool
        $ruleSetting = \App\Models\RuleSetting::create([
            'name' => 'Test Rules',
            'points_per_goal' => 2,
            'points_per_assist' => 1,
            'points_per_shutout' => 3,
            'points_per_victory' => 2,
            'points_per_defeat' => 0,
            'points_per_overtime' => 1,
        ]);

        $pool = \App\Models\Pool::create([
            'name' => 'Test Pool',
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(7),
            'rule_setting_id' => $ruleSetting->id,
            'status' => 'upcoming',
        ]);

        $pool->users()->attach($user->id);

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard/Index')
            ->has('pools')
            ->where('pools.0.name', 'Test Pool')
            ->where('pools.0.participants_count', 1)
        );
    }
}
