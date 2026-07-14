<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\StaffNoc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;

/**
 * AuthController — Login + TOTP MFA.
 *
 * Two-step authentication flow:
 * 1. POST /api/auth/login — Validasi email + password, return TOTP challenge
 * 2. POST /api/auth/totp-verify — Validasi TOTP code, return Sanctum token
 *
 * @see SECURITY.md Section 3.1
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly Google2FA $google2fa
    ) {}

    /**
     * Step 1: Login dengan email + password.
     *
     * Jika valid, return TOTP challenge (bukan token).
     * Token HANYA diberikan setelah TOTP berhasil diverifikasi.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Rate limiting login attempts
        $throttleKey = 'login:' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
            ], 429);
        }

        $staff = StaffNoc::where('email', $request->email)->first();

        if (! $staff || ! Hash::check($request->password, $staff->password_hash)) {
            RateLimiter::hit($throttleKey, 300); // 5 menit decay

            return response()->json([
                'message' => 'Email atau password salah.',
            ], 401);
        }

        if (! $staff->is_active) {
            return response()->json([
                'message' => 'Akun Anda telah dinonaktifkan. Hubungi administrator.',
            ], 403);
        }

        // Cek apakah TOTP sudah di-setup
        if (empty($staff->totp_secret_encrypted)) {
            return response()->json([
                'message' => 'TOTP belum dikonfigurasi untuk akun ini. Hubungi administrator.',
            ], 403);
        }

        // Clear rate limiter on successful password
        RateLimiter::clear($throttleKey);

        // Generate temporary challenge token (berlaku 5 menit)
        $challengeToken = $staff->createToken(
            'totp-challenge',
            ['totp-verify'],
            now()->addMinutes(5)
        );

        AuditLog::record(
            staffId: $staff->id,
            action: 'AUTH_LOGIN_PASSWORD_OK',
            metadata: [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        );

        return response()->json([
            'message' => 'Password valid. Masukkan kode TOTP.',
            'totp_required' => true,
            'challenge_token' => $challengeToken->plainTextToken,
        ]);
    }

    /**
     * Step 2: Verifikasi TOTP code.
     *
     * Memerlukan challenge_token dari step 1.
     * Jika TOTP valid, hapus challenge_token dan buat session token baru.
     *
     * @see SECURITY.md Section 3.1 — Session token expired setelah 8 jam tidak aktif
     */
    public function verifyTotp(Request $request): JsonResponse
    {
        $request->validate([
            'totp_code' => 'required|string|size:6',
        ]);

        $staff = $request->user();

        if (! $staff) {
            return response()->json([
                'message' => 'Challenge token invalid atau expired.',
            ], 401);
        }

        // Pastikan token hanya memiliki ability totp-verify
        if (! $staff->currentAccessToken()->can('totp-verify')) {
            return response()->json([
                'message' => 'Token tidak valid untuk verifikasi TOTP.',
            ], 403);
        }

        // Verifikasi TOTP
        $totpSecret = $staff->totp_secret;

        if (! $totpSecret) {
            return response()->json([
                'message' => 'TOTP belum dikonfigurasi.',
            ], 403);
        }

        $valid = $this->google2fa->verifyKey(
            $totpSecret,
            $request->totp_code,
            config('netsight.auth.totp_window')
        );

        if (! $valid) {
            AuditLog::record(
                staffId: $staff->id,
                action: 'AUTH_TOTP_FAILED',
                metadata: [
                    'ip_address' => $request->ip(),
                ]
            );

            return response()->json([
                'message' => 'Kode TOTP tidak valid.',
            ], 401);
        }

        // Hapus challenge token
        $staff->currentAccessToken()->delete();

        // Buat session token dengan abilities penuh sesuai role
        $sessionTimeout = config('netsight.auth.session_timeout_hours');
        $sessionToken = $staff->createToken(
            'session',
            ['*'], // Full abilities, RBAC ditangani oleh middleware
            now()->addHours($sessionTimeout)
        );

        AuditLog::record(
            staffId: $staff->id,
            action: 'AUTH_LOGIN_SUCCESS',
            metadata: [
                'ip_address' => $request->ip(),
                'session_expires_at' => now()->addHours($sessionTimeout)->toIso8601String(),
            ]
        );

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $sessionToken->plainTextToken,
            'user' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'role' => $staff->role,
            ],
            'expires_at' => now()->addHours($sessionTimeout)->toIso8601String(),
        ]);
    }

    /**
     * Logout — Revoke current session token.
     */
    public function logout(Request $request): JsonResponse
    {
        $staff = $request->user();

        if ($staff) {
            AuditLog::record(
                staffId: $staff->id,
                action: 'AUTH_LOGOUT',
                metadata: [
                    'ip_address' => $request->ip(),
                ]
            );

            $staff->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }

    /**
     * Get current authenticated user info.
     */
    public function me(Request $request): JsonResponse
    {
        $staff = $request->user();

        return response()->json([
            'user' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'role' => $staff->role,
            ],
        ]);
    }

    /**
     * Setup TOTP untuk user baru (hanya ADMIN yang bisa trigger).
     */
    public function setupTotp(Request $request): JsonResponse
    {
        $request->validate([
            'staff_id' => 'required|exists:staff_noc,id',
        ]);

        $staff = StaffNoc::findOrFail($request->staff_id);

        // Generate TOTP secret baru
        $secret = $this->google2fa->generateSecretKey();

        // Simpan terenkripsi via mutator
        $staff->totp_secret = $secret;
        $staff->save();

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            'NETSIGHT',
            $staff->email,
            $secret
        );

        AuditLog::record(
            staffId: $request->user()->id,
            action: 'ADMIN_SETUP_TOTP',
            targetUsername: $staff->email,
            metadata: [
                'target_staff_id' => $staff->id,
            ]
        );

        return response()->json([
            'message' => 'TOTP secret generated.',
            'secret' => $secret, // Tampilkan sekali saja saat setup
            'qr_code_url' => $qrCodeUrl,
        ]);
    }
}
