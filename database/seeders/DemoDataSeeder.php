<?php

namespace Database\Seeders;

use App\Models\StaffNoc;
use App\Models\Router;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create ADMIN User
        StaffNoc::updateOrCreate(
            ['email' => 'admin@netsight.local'],
            [
                'name' => 'NOC Admin',
                'password_hash' => Hash::make('password123'),
                'totp_secret' => 'JBSWY3DPEHPK3PXP', // Known TOTP base32 key for testing
                'role' => 'ADMIN',
                'is_active' => true,
            ]
        );

        // 2. Create TIER_2 User
        StaffNoc::updateOrCreate(
            ['email' => 'noc@netsight.local'],
            [
                'name' => 'NOC Engineer L2',
                'password_hash' => Hash::make('password123'),
                'totp_secret' => 'JBSWY3DPEHPK3PXP',
                'role' => 'TIER_2',
                'is_active' => true,
            ]
        );

        // 3. Create a Demo Router (pointing to localhost or dummy)
        Router::updateOrCreate(
            ['host' => '127.0.0.1'],
            [
                'name' => 'NOC Edge Router 01',
                'api_port' => 8729,
                'credential' => 'mikrotik_api_pass_123',
                'sync_offset_minutes' => 15,
                'status' => 'HEALTHY',
            ]
        );
    }
}
