<?php

namespace Database\Seeders;

use Vandiza\NetsightCore\Models\StaffNoc;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantInitialSeeder extends Seeder
{
    /**
     * Run the database seeds for a new tenant onboarding.
     */
    public function run(): void
    {
        // Create initial ADMIN Account
        StaffNoc::updateOrCreate(
            ['email' => 'admin@netsight.xyz'],
            [
                'name' => 'Tenant Admin',
                'password_hash' => Hash::make('admin'),
                'totp_secret' => null,
                'role' => 'ADMIN',
                'is_active' => true,
                'must_change_password' => true,
            ]
        );
    }
}
