<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index(): Response
    {
        $currentUser = auth()->user();

        // Never show super admins in the list
        $users = User::with('roles')
            ->withCount('pools')
            ->where('is_super_admin', false)
            ->get();

        // Only super admins can see/assign the admin role
        $roles = $currentUser->is_super_admin
            ? Role::all()
            : Role::where('name', '!=', 'admin')->get();

        return Inertia::render('admin/Users', [
            'users' => $users,
            'roles' => $roles,
            'is_super_admin' => $currentUser->is_super_admin,
        ]);
    }

    /**
     * Update the specified user's role.
     */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $currentUser = auth()->user();

        // Protect super admin
        if ($user->is_super_admin) {
            return Redirect::back()->withErrors([
                'role' => 'Impossible de modifier le rôle du super administrateur.',
            ]);
        }

        // Only super admins can manage other admins
        if ($user->hasRole('admin') && ! $currentUser->is_super_admin) {
            return Redirect::back()->withErrors([
                'role' => 'Seul le super administrateur peut modifier les administrateurs.',
            ]);
        }

        $validated = $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        // Only super admins can assign the admin role
        if ($validated['role'] === 'admin' && ! $currentUser->is_super_admin) {
            return Redirect::back()->withErrors([
                'role' => 'Seul le super administrateur peut assigner le rôle d\'administrateur.',
            ]);
        }

        $user->syncRoles([$validated['role']]);

        return Redirect::route('admin.users.index');
    }

    /**
     * Ban or unban the specified user.
     */
    public function toggleBan(User $user): RedirectResponse
    {
        $currentUser = auth()->user();

        // Protect super admin
        if ($user->is_super_admin) {
            return Redirect::back()->withErrors([
                'ban' => 'Impossible de bannir le super administrateur.',
            ]);
        }

        // Only super admins can ban other admins
        if ($user->hasRole('admin') && ! $currentUser->is_super_admin) {
            return Redirect::back()->withErrors([
                'ban' => 'Seul le super administrateur peut bannir les administrateurs.',
            ]);
        }

        $user->update([
            'is_banned' => ! $user->is_banned,
            'banned_at' => $user->is_banned ? null : now(),
        ]);

        return Redirect::route('admin.users.index');
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user): RedirectResponse
    {
        $currentUser = auth()->user();

        // Protect super admin
        if ($user->is_super_admin) {
            return Redirect::back()->withErrors([
                'delete' => 'Impossible de supprimer le super administrateur.',
            ]);
        }

        // Prevent deleting own account
        if ($user->id === $currentUser->id) {
            return Redirect::back()->withErrors([
                'delete' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ]);
        }

        // Only super admins can delete other admins
        if ($user->hasRole('admin') && ! $currentUser->is_super_admin) {
            return Redirect::back()->withErrors([
                'delete' => 'Seul le super administrateur peut supprimer les administrateurs.',
            ]);
        }

        $user->delete();

        return Redirect::route('admin.users.index');
    }
}
