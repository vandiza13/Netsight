<?php

namespace Database\Seeders;

use Vandiza\NetsightCore\Models\StaffNoc;
use Vandiza\NetsightCore\Models\Router;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create ADMIN User
        StaffNoc::updateOrCreate(
            ['email' => 'admin@netsight.xyz'],
            [
                'name' => 'NOC Admin',
                'password_hash' => Hash::make('admin'),
                'totp_secret' => null,
                'role' => 'ADMIN',
                'is_active' => true,
                'must_change_password' => true,
            ]
        );

        // 2. Create TIER_2 User
        StaffNoc::updateOrCreate(
            ['email' => 'noc@netsight.xyz'],
            [
                'name' => 'NOC Engineer L2',
                'password_hash' => Hash::make('noc'),
                'totp_secret' => null,
                'role' => 'TIER_2',
                'is_active' => true,
                'must_change_password' => true,
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

        // 4. Create Demo ACS TR-069 Devices
        \Vandiza\NetsightCore\Models\AcsDevice::updateOrCreate(
            ['genieacs_id' => 'ZTEG-C1234567'],
            [
                'serial_number' => 'ZTEGC1234567',
                'mac_address' => 'CC:2D:E0:11:22:33',
                'vendor' => 'ZTE',
                'model' => 'F660 v6.0',
                'hardware_version' => 'V6.0',
                'software_version' => 'V6.0.10P1T1',
                'ip_address' => '10.10.20.45',
                'rx_power_dbm' => -19.45,
                'wifi_ssid' => 'NETSIGHT_FIBER_HOME',
                'pppoe_username' => 'budi_santoso@netsight',
                'status' => 'online',
                'last_inform_at' => now(),
            ]
        );

        \Vandiza\NetsightCore\Models\AcsDevice::updateOrCreate(
            ['genieacs_id' => 'HWTC-87654321'],
            [
                'serial_number' => 'HWTC87654321',
                'mac_address' => '00:1E:10:99:88:77',
                'vendor' => 'Huawei',
                'model' => 'HG8245H',
                'hardware_version' => 'V3R015C10',
                'software_version' => 'V300R015C10SPC130',
                'ip_address' => '10.10.20.88',
                'rx_power_dbm' => -26.20,
                'wifi_ssid' => 'SITI_WIFI_5G',
                'pppoe_username' => 'siti_rahma@netsight',
                'status' => 'online',
                'last_inform_at' => now(),
            ]
        );

        \Vandiza\NetsightCore\Models\AcsDevice::updateOrCreate(
            ['genieacs_id' => 'FHTT-99001122'],
            [
                'serial_number' => 'FHTT99001122',
                'mac_address' => '54:B8:0A:44:55:66',
                'vendor' => 'FiberHome',
                'model' => 'HG6245D',
                'hardware_version' => 'V1.0',
                'software_version' => 'RP2612',
                'ip_address' => '10.10.20.102',
                'rx_power_dbm' => -31.80,
                'wifi_ssid' => 'AGUS_NET',
                'pppoe_username' => 'agus_setiawan@netsight',
                'status' => 'offline',
                'last_inform_at' => now()->subHours(5),
            ]
        );
    }
}
