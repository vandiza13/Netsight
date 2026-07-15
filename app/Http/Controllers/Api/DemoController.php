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
        // 1. Ambil schema yang idle
        $sandbox = DB::table('public.demo_sandboxes')
            ->where('status', 'idle')
            ->orderBy('id', 'asc')
            ->first();
            
        if (!$sandbox) {
            // Jika pool kosong, fallback error.
            return response()->json(['message' => 'Server demo sedang menyiapkan sesi baru. Silakan coba lagi dalam beberapa detik.'], 503);
        }

        $schema = $sandbox->schema_name;

        // 2. Tandai schema menjadi active
        DB::table('public.demo_sandboxes')
            ->where('id', $sandbox->id)
            ->update([
                'status' => 'active',
                'expires_at' => now()->addHours(24),
                'updated_at' => now(),
            ]);
            
        // 3. Ambil TOTP Secret dari dalam schema tersebut
        config(['database.connections.pgsql.search_path' => $schema]);
        DB::purge('pgsql');
        
        $admin = StaffNoc::where('email', 'demo@netsight.id')->first();
        $totpSecret = $admin->totp_secret;
        
        config(['database.connections.pgsql.search_path' => 'public']);
        DB::purge('pgsql');

        // 4. Generate QR Code
        $google2fa = new Google2FA();
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            'Netsight Demo',
            $admin->email,
            $totpSecret
        );

        return response()->json([
            'message' => 'Sandbox berhasil diklaim.',
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
