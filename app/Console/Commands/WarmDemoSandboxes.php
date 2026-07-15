<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use App\Models\StaffNoc;
use App\Models\Router;
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
        $targetPoolSize = 3;
        
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

        // 5. Create default admin user in this schema
        $google2fa = new Google2FA();
        $totpSecret = $google2fa->generateSecretKey();

        $admin = new StaffNoc([
            'name' => 'Demo Admin',
            'email' => 'demo@netsight.id',
            'password_hash' => Hash::make('password123'),
            'role' => 'ADMIN',
            'is_active' => true,
        ]);
        $admin->totp_secret = $totpSecret;
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
        $router->save();

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
