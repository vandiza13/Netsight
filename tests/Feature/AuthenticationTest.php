<?php

use App\Models\StaffNoc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

function createAuthStaff(array $overrides = []): StaffNoc
{
    $google2fa = new Google2FA();
    $secret = $google2fa->generateSecretKey();

    return StaffNoc::create(array_merge([
        'name' => 'Test User',
        'email' => 'test@netsight.local',
        'password_hash' => Hash::make('SecurePass123!'),
        'totp_secret_encrypted' => Crypt::encryptString($secret),
        'role' => 'TIER_2',
        'is_active' => true,
    ], $overrides));
}

it('rejects login with wrong password', function () {
    createAuthStaff();

    $response = $this->postJson('/api/auth/login', [
        'email' => 'test@netsight.local',
        'password' => 'wrong_password',
    ]);

    $response->assertStatus(401)
        ->assertJson(['message' => 'Email atau password salah.']);
});

it('returns totp_required on correct password', function () {
    createAuthStaff();

    $response = $this->postJson('/api/auth/login', [
        'email' => 'test@netsight.local',
        'password' => 'SecurePass123!',
    ]);

    $response->assertStatus(200)
        ->assertJson(['totp_required' => true])
        ->assertJsonStructure(['challenge_token']);
});

it('rejects invalid TOTP code', function () {
    $staff = createAuthStaff();
    $challengeToken = $staff->createToken('totp-challenge', ['totp-verify'], now()->addMinutes(5));

    $response = $this->withHeader('Authorization', 'Bearer ' . $challengeToken->plainTextToken)
        ->postJson('/api/auth/totp-verify', [
            'totp_code' => '000000',
        ]);

    $response->assertStatus(401)
        ->assertJson(['message' => 'Kode TOTP tidak valid.']);
});

it('grants session token on valid TOTP', function () {
    $google2fa = new Google2FA();
    $secret = $google2fa->generateSecretKey();
    $validCode = $google2fa->getCurrentOtp($secret);

    $staff = createAuthStaff([
        'totp_secret_encrypted' => Crypt::encryptString($secret),
    ]);

    $challengeToken = $staff->createToken('totp-challenge', ['totp-verify'], now()->addMinutes(5));

    $response = $this->withHeader('Authorization', 'Bearer ' . $challengeToken->plainTextToken)
        ->postJson('/api/auth/totp-verify', [
            'totp_code' => $validCode,
        ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['token', 'user', 'expires_at'])
        ->assertJson(['user' => ['role' => 'TIER_2']]);
});

it('rejects login for inactive account', function () {
    createAuthStaff(['is_active' => false]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'test@netsight.local',
        'password' => 'SecurePass123!',
    ]);

    $response->assertStatus(403)
        ->assertJson(['message' => 'Akun Anda telah dinonaktifkan. Hubungi administrator.']);
});

it('rejects login without TOTP configured', function () {
    createAuthStaff(['totp_secret_encrypted' => null]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'test@netsight.local',
        'password' => 'SecurePass123!',
    ]);

    $response->assertStatus(403);
});
