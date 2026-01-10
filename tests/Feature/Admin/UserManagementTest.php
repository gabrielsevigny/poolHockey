<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'user', 'guard_name' => 'web']);
    }

    public function test_admin_can_view_users_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('admin/Users'));
    }

    public function test_non_admin_cannot_access_users_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $response = $this->actingAs($user)->get(route('admin.users.index'));

        $response->assertStatus(403);
    }

    public function test_admin_can_update_user_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $user = User::factory()->create();

        $response = $this->actingAs($admin)
            ->patch(route('admin.users.update-role', $user), [
                'role' => 'user',
            ]);

        $response->assertRedirect(route('admin.users.index'));

        $this->assertTrue($user->fresh()->hasRole('user'));
    }

    public function test_only_super_admin_can_assign_admin_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $user = User::factory()->create();
        $user->assignRole('user');

        // Regular admin cannot assign admin role
        $response = $this->actingAs($admin)
            ->patch(route('admin.users.update-role', $user), [
                'role' => 'admin',
            ]);

        $response->assertSessionHasErrors('role');
        $this->assertFalse($user->fresh()->hasRole('admin'));

        // Super admin can assign admin role
        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $superAdmin->assignRole('admin');

        $response = $this->actingAs($superAdmin)
            ->patch(route('admin.users.update-role', $user), [
                'role' => 'admin',
            ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertTrue($user->fresh()->hasRole('admin'));
    }

    public function test_super_admin_is_hidden_from_users_list(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $superAdmin->assignRole('admin');

        $regularAdmin = User::factory()->create();
        $regularAdmin->assignRole('admin');

        $regularUser = User::factory()->create();
        $regularUser->assignRole('user');

        $response = $this->actingAs($regularAdmin)
            ->get(route('admin.users.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('admin/Users')
            ->has('users', 2) // Regular admin and regular user should be visible
            ->where('users.0.id', $regularAdmin->id)
            ->where('users.1.id', $regularUser->id)
        );
    }

    public function test_super_admin_cannot_be_modified(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $superAdmin->assignRole('admin');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Cannot change super admin's role
        $response = $this->actingAs($admin)
            ->patch(route('admin.users.update-role', $superAdmin), [
                'role' => 'user',
            ]);

        $response->assertSessionHasErrors('role');

        // Cannot ban super admin
        $response = $this->actingAs($admin)
            ->patch(route('admin.users.toggle-ban', $superAdmin));

        $response->assertSessionHasErrors('ban');

        // Cannot delete super admin
        $response = $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $superAdmin));

        $response->assertSessionHasErrors('delete');
    }

    public function test_admin_can_ban_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $user = User::factory()->create(['is_banned' => false]);

        $response = $this->actingAs($admin)
            ->patch(route('admin.users.toggle-ban', $user));

        $response->assertRedirect(route('admin.users.index'));

        $this->assertTrue($user->fresh()->is_banned);
        $this->assertNotNull($user->fresh()->banned_at);
    }

    public function test_admin_can_unban_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $user = User::factory()->create([
            'is_banned' => true,
            'banned_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('admin.users.toggle-ban', $user));

        $response->assertRedirect(route('admin.users.index'));

        $this->assertFalse($user->fresh()->is_banned);
        $this->assertNull($user->fresh()->banned_at);
    }

    public function test_admin_can_delete_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $user = User::factory()->create();

        $response = $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $user));

        $response->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->delete(route('admin.users.destroy', $admin));

        $response->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
        ]);
    }
}
