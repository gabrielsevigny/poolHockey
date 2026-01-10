<?php

namespace Tests\Feature;

use App\Models\Pool;
use App\Models\RuleSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoolCreationTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Create default rule settings
        RuleSetting::create([
            'name' => 'Standard',
            'points_per_goal' => 2,
            'points_per_assist' => 1,
            'points_per_shutout' => 3,
            'points_per_victory' => 2,
            'points_per_defeat' => 0,
            'points_per_overtime' => 1,
        ]);
    }

    public function test_authenticated_user_can_create_a_pool(): void
    {
        $user = User::factory()->create();
        $participant1 = User::factory()->create();
        $participant2 = User::factory()->create();
        $ruleSetting = RuleSetting::first();

        $startDate = now()->addDays(7)->format('Y-m-d');
        $endDate = now()->addDays(14)->format('Y-m-d');

        $response = $this->actingAs($user)
            ->post(route('pools.store'), [
                'name' => 'Pool de la semaine',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'rule_setting_id' => $ruleSetting->id,
                'user_ids' => [$participant1->id, $participant2->id],
            ]);

        $response->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('pools', [
            'name' => 'Pool de la semaine',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'rule_setting_id' => $ruleSetting->id,
            'status' => 'upcoming',
        ]);

        $pool = Pool::where('name', 'Pool de la semaine')->first();
        $this->assertCount(2, $pool->users);
        $this->assertTrue($pool->users->contains($participant1));
        $this->assertTrue($pool->users->contains($participant2));
    }

    public function test_pool_name_is_required(): void
    {
        $user = User::factory()->create();
        $ruleSetting = RuleSetting::first();

        $response = $this->actingAs($user)
            ->withoutMiddleware()
            ->post(route('pools.store'), [
                'name' => '',
                'start_date' => '2025-01-01',
                'end_date' => '2025-01-07',
                'rule_setting_id' => $ruleSetting->id,
                'user_ids' => [$user->id],
            ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_end_date_must_be_after_start_date(): void
    {
        $user = User::factory()->create();
        $ruleSetting = RuleSetting::first();

        $response = $this->actingAs($user)
            ->withoutMiddleware()
            ->post(route('pools.store'), [
                'name' => 'Pool Test',
                'start_date' => '2025-01-07',
                'end_date' => '2025-01-01',
                'rule_setting_id' => $ruleSetting->id,
                'user_ids' => [$user->id],
            ]);

        $response->assertSessionHasErrors('end_date');
    }

    public function test_pool_requires_at_least_one_participant(): void
    {
        $user = User::factory()->create();
        $ruleSetting = RuleSetting::first();

        $response = $this->actingAs($user)
            ->withoutMiddleware()
            ->post(route('pools.store'), [
                'name' => 'Pool Test',
                'start_date' => '2025-01-01',
                'end_date' => '2025-01-07',
                'rule_setting_id' => $ruleSetting->id,
                'user_ids' => [],
            ]);

        $response->assertSessionHasErrors('user_ids');
    }

    public function test_guests_cannot_create_pools(): void
    {
        $ruleSetting = RuleSetting::first();
        $user = User::factory()->create();

        $poolCountBefore = Pool::count();

        $this->post(route('pools.store'), [
            'name' => 'Pool Test',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-07',
            'rule_setting_id' => $ruleSetting->id,
            'user_ids' => [$user->id],
        ]);

        // Pool should not be created
        $this->assertEquals($poolCountBefore, Pool::count());
    }
}
