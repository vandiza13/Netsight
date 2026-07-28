<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Vandiza\NetsightCore\Models\StaffNoc;
use Vandiza\NetsightCore\Models\Router;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class WarmDemoSandboxes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:warm-demo-sandboxes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Maintain a pool of idle demo sandboxes for instant provisioning';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $targetPoolSize = 20;
        
        $idleCount = DB::table('public.demo_sandboxes')
            ->where('status', 'idle')
            ->count();

        $needed = $targetPoolSize - $idleCount;

        if ($needed <= 0) {
            $this->info("Pool is full ({$idleCount} idle sandboxes). No action needed.");
            return;
        }

        $this->info("Pool needs {$needed} sandboxes. Warming up...");

        for ($i = 0; $i < $needed; $i++) {
            $this->createSandbox();
        }

        $this->info("Successfully warmed up {$needed} sandboxes.");
    }

    private function createSandbox()
    {
        // 1. Generate unique schema name
        $schema = 'demo_' . strtolower(Str::random(8));
        $this->info("Creating schema: {$schema}");

        // 2. Create Schema on current connection
        DB::statement("CREATE SCHEMA {$schema}");

        // 3. Reconnect with new search path to run migrations
        config(['database.connections.pgsql.search_path' => $schema]);
        DB::purge('pgsql');

        // 4. Run migrations on the new schema
        Artisan::call('migrate', [
            '--force' => true,
        ]);

        // 5. Create default admin user in this schema (no TOTP required for instant demo access)
        $admin = new StaffNoc([
            'name' => 'Demo Admin',
            'email' => 'demo@netsight.id',
            'password_hash' => Hash::make('password123'),
            'role' => 'ADMIN',
            'is_active' => true,
        ]);
        $admin->save();

        // 6. Seed a Dummy Router for the sandbox
        $router = new Router();
        $router->name = 'Simulated Core Router (Demo)';
        $router->host = '192.168.88.1';
        $router->api_user = 'admin';
        $router->api_port = 8728;
        $router->credential = 'demo';
        $router->sync_offset_minutes = 0;
        $router->status = 'HEALTHY';
        $router->routeros_version = '7.12.1';
        $router->last_synced_at = now()->subMinutes(2);
        $router->save();

        // 6a. Seed Dummy PPPoE Users
        $profiles = ['10M', '20M', '50M', '100M'];
        $users = [];
        for ($i = 1; $i <= 45; $i++) {
            $prof = $profiles[array_rand($profiles)];
            $limit = (int)str_replace('M', '', $prof);
            
            $users[] = [
                'router_id' => $router->id,
                'username' => 'user' . str_pad($i, 3, '0', STR_PAD_LEFT) . '@demo.net',
                'profile' => $prof,
                'package_limit_mbps' => $limit,
                'is_active_last_check' => (rand(1, 10) > 2), // 80% active
                'synced_at' => now()->subMinutes(rand(1, 10)),
            ];
        }
        DB::table('pppoe_users_cache')->insert($users);

        // 6b. Seed Dummy Torch Sessions History
        $sessions = [];
        for ($i = 1; $i <= 5; $i++) {
            $avgTx = rand(5000000, 20000000); // 5 - 20 Mbps download (TX)
            $avgRx = rand(1000000, 4000000);  // 1 - 4 Mbps upload (RX)
            
            $sessions[] = [
                'router_id' => $router->id,
                'username' => 'user' . str_pad(rand(1, 45), 3, '0', STR_PAD_LEFT) . '@demo.net',
                'session_id_snapshot' => '819200' . rand(10, 99),
                'dynamic_interface' => '<pppoe-user>',
                'initiated_by' => $admin->id,
                'tag' => 'demo_torch_' . \Illuminate\Support\Str::random(5),
                'status' => 'COMPLETED',
                'auto_cleanup' => true,
                'started_at' => now()->subHours(rand(1, 48)),
                'ended_at' => now()->subHours(rand(1, 48))->addMinutes(2),
                'diagnostic_conclusion' => 'Koneksi normal. Latensi dan bandwidth terpantau stabil.',
                'peak_tx_bps' => (int)($avgTx * 1.5),
                'peak_rx_bps' => (int)($avgRx * 1.5),
                'avg_tx_bps' => $avgTx,
                'avg_rx_bps' => $avgRx,
                'app_distribution' => json_encode([
                    ['name' => 'YouTube', 'bytes' => (int)($avgRx * 0.6), 'percentage' => 60],
                    ['name' => 'TikTok', 'bytes' => (int)($avgRx * 0.3), 'percentage' => 30],
                    ['name' => 'Unknown', 'bytes' => (int)($avgRx * 0.1), 'percentage' => 10],
                ])
            ];
        }
        DB::table('torch_sessions')->insert($sessions);

        // 7. Track the schema in public database as 'idle'
        // Reset search path back to public first just to be safe
        config(['database.connections.pgsql.search_path' => 'public']);
        DB::purge('pgsql');

        // We need a way to store the admin's TOTP secret so we can hand it out when someone claims the sandbox.
        // But wait! We didn't add a column for admin_totp_secret in the migration.
        // We can just add it now by modifying the migration, but it's already run.
        // Or we can just read it from the schema's users table when someone claims it! Yes, that's better.

        DB::table('public.demo_sandboxes')->insert([
            'schema_name' => $schema,
            'status' => 'idle',
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
