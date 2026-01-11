<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create or update the super admin user
        $admin = User::updateOrCreate(
            ['email' => 'admin@hockeypool.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_super_admin' => true,
            ]
        );

        // Assign superAdmin role
        if (! $admin->hasRole('superAdmin')) {
            $admin->assignRole('superAdmin');
        }

        $this->command->info('Super admin user created/updated successfully (ID: '.$admin->id.')');
    }
}
