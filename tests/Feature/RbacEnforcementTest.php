<?php

use App\Models\StaffNoc;
use App\Models\Router;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function createStaff(string $role): StaffNoc
{
    return StaffNoc::create([
        'name' => "Test {$role}",
        'email' => strtolower($role) . '@test.com',
        'password_hash' => Hash::make('password123!A'),
        'totp_secret_encrypted' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
        'role' => $role,
        'is_active' => true,
    ]);
}

function createRouter(): Router
{
    return Router::create([
        'name' => 'Test Router',
        'host' => '192.168.1.1',
        'api_port' => 8729,
        'credential_encrypted' => Crypt::encryptString('test_password'),
        'sync_offset_minutes' => 0,
        'status' => 'HEALTHY',
    ]);
}

// ---- TIER_1 restrictions ----

it('returns 403 when TIER_1 calls torch inspect endpoint', function () {
    $staff = createStaff('TIER_1');
    $router = createRouter();
    $token = $staff->createToken('test', ['*'])->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/torch/inspect', [
            'router_id' => $router->id,
            'username' => 'testuser',
        ]);

    $response->assertStatus(403);
});

it('returns 403 when TIER_1 calls force-sync endpoint', function () {
    $staff = createStaff('TIER_1');
    $router = createRouter();
    $token = $staff->createToken('test', ['*'])->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/routers/{$router->id}/force-sync");

    $response->assertStatus(403);
});

it('returns 403 when TIER_1 calls health-check endpoint', function () {
    $staff = createStaff('TIER_1');
    $router = createRouter();
    $token = $staff->createToken('test', ['*'])->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/routers/{$router->id}/health-check");

    $response->assertStatus(403);
});

it('returns 403 when TIER_1 calls audit-logs endpoint', function () {
    $staff = createStaff('TIER_1');
    $token = $staff->createToken('test', ['*'])->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/audit-logs');

    $response->assertStatus(403);
});

// ---- TIER_2 restrictions ----

it('returns 403 when TIER_2 calls audit-logs endpoint', function () {
    $staff = createStaff('TIER_2');
    $token = $staff->createToken('test', ['*'])->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/audit-logs');

    $response->assertStatus(403);
});

// ---- TIER_1 CAN access own tier ----

it('allows TIER_1 to access routers list', function () {
    $staff = createStaff('TIER_1');
    $token = $staff->createToken('test', ['*'])->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/routers');

    $response->assertStatus(200);
});

it('allows TIER_1 to access router users', function () {
    $staff = createStaff('TIER_1');
    $router = createRouter();
    $token = $staff->createToken('test', ['*'])->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/routers/{$router->id}/users");

    $response->assertStatus(200);
});

// ---- ADMIN can access all ----

it('allows ADMIN to access all endpoints', function () {
    $staff = createStaff('ADMIN');
    $router = createRouter();
    $token = $staff->createToken('test', ['*'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/routers')
        ->assertStatus(200);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/audit-logs')
        ->assertStatus(200);
});

// ---- Unauthenticated ----

it('returns 401 for unauthenticated requests', function () {
    $this->getJson('/api/routers')->assertStatus(401);
    $this->postJson('/api/torch/inspect')->assertStatus(401);
    $this->getJson('/api/audit-logs')->assertStatus(401);
});
