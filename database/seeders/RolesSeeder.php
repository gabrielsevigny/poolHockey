<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'superAdmin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'poolAdmin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'participant', 'guard_name' => 'web']);
    }
}
