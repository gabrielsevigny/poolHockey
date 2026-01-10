<?php

namespace Tests\Feature\Admin;

use App\Models\RuleSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RuleSettingManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'user', 'guard_name' => 'web']);
    }

    public function test_admin_can_view_rule_settings_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get(route('admin.rule-settings.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('admin/RuleSettings'));
    }

    public function test_non_admin_cannot_access_rule_settings_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $response = $this->actingAs($user)->get(route('admin.rule-settings.index'));

        $response->assertStatus(403);
    }

    public function test_admin_can_create_rule_setting(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)
            ->post(route('admin.rule-settings.store'), [
                'name' => 'Test Rules',
                'points_per_goal' => 3,
                'points_per_assist' => 2,
                'points_per_shutout' => 5,
                'points_per_victory' => 3,
                'points_per_defeat' => -1,
                'points_per_overtime' => 2,
            ]);

        $response->assertRedirect(route('admin.rule-settings.index'));

        $this->assertDatabaseHas('rule_settings', [
            'name' => 'Test Rules',
            'points_per_goal' => 3,
        ]);
    }

    public function test_admin_can_update_rule_setting(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $ruleSetting = RuleSetting::create([
            'name' => 'Original',
            'points_per_goal' => 2,
            'points_per_assist' => 1,
            'points_per_shutout' => 3,
            'points_per_victory' => 2,
            'points_per_defeat' => 0,
            'points_per_overtime' => 1,
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('admin.rule-settings.update', $ruleSetting), [
                'name' => 'Updated',
                'points_per_goal' => 3,
                'points_per_assist' => 2,
                'points_per_shutout' => 5,
                'points_per_victory' => 3,
                'points_per_defeat' => -1,
                'points_per_overtime' => 2,
            ]);

        $response->assertRedirect(route('admin.rule-settings.index'));

        $this->assertDatabaseHas('rule_settings', [
            'id' => $ruleSetting->id,
            'name' => 'Updated',
            'points_per_goal' => 3,
        ]);
    }

    public function test_admin_can_delete_unused_rule_setting(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $ruleSetting = RuleSetting::create([
            'name' => 'To Delete',
            'points_per_goal' => 2,
            'points_per_assist' => 1,
            'points_per_shutout' => 3,
            'points_per_victory' => 2,
            'points_per_defeat' => 0,
            'points_per_overtime' => 1,
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.rule-settings.destroy', $ruleSetting));

        $response->assertRedirect(route('admin.rule-settings.index'));

        $this->assertDatabaseMissing('rule_settings', [
            'id' => $ruleSetting->id,
        ]);
    }
}
