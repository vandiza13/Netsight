<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use App\Models\StaffNoc;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class DemoController extends Controller
{
    public function start(Request $request)
    {
        // 1. Generate unique schema name
        $schema = 'demo_' . strtolower(Str::random(8));

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

        // 6. Generate QR code URL (using a free QR code generator API for the frontend to display)
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            'Netsight Demo',
            $admin->email,
            $totpSecret
        );
        // PragmaRX returns a otpauth:// URL, we can format it for the frontend
        // If we want a direct image URL, we can use google charts or similar, but the frontend usually handles it.
        // Actually, our frontend expects totp_qr_url to be the otpauth:// URI and uses qrcode.vue to render it.

        // 7. Track the schema in public database
        DB::table('public.demo_sandboxes')->insert([
            'schema_name' => $schema,
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Sandbox created successfully.',
            'schema' => $schema,
            'admin' => [
                'email' => $admin->email,
                'password' => 'password123',
                'totp_secret' => $totpSecret,
                'totp_qr_url' => $qrCodeUrl,
            ],
        ]);
    }
}
