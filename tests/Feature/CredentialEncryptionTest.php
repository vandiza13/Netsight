<?php

use App\Models\Router;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('stores router credential as encrypted text in database', function () {
    $plainPassword = 'my_secret_router_password';

    $router = Router::create([
        'name' => 'Test Router',
        'host' => '192.168.1.1',
        'api_port' => 8729,
        'credential_encrypted' => Crypt::encryptString($plainPassword),
        'sync_offset_minutes' => 0,
        'status' => 'HEALTHY',
    ]);

    // Verifikasi bahwa di database BUKAN plain text
    $rawValue = DB::table('routers')
        ->where('id', $router->id)
        ->value('credential_encrypted');

    expect($rawValue)->not->toBe($plainPassword);
    expect($rawValue)->not->toBeNull();
    expect($rawValue)->not->toBeEmpty();
});

it('decrypts router credential correctly via accessor', function () {
    $plainPassword = 'my_secret_router_password';

    $router = Router::create([
        'name' => 'Test Router',
        'host' => '192.168.1.1',
        'api_port' => 8729,
        'credential_encrypted' => Crypt::encryptString($plainPassword),
        'sync_offset_minutes' => 0,
        'status' => 'HEALTHY',
    ]);

    // Refresh dari database
    $router->refresh();

    expect($router->credential)->toBe($plainPassword);
});

it('never exposes credential_encrypted in JSON serialization', function () {
    $router = Router::create([
        'name' => 'Test Router',
        'host' => '192.168.1.1',
        'api_port' => 8729,
        'credential_encrypted' => Crypt::encryptString('secret'),
        'sync_offset_minutes' => 0,
        'status' => 'HEALTHY',
    ]);

    $json = $router->toArray();

    expect($json)->not->toHaveKey('credential_encrypted');
    expect($json)->not->toHaveKey('credential');
});

it('encrypts credential via model mutator', function () {
    $router = new Router();
    $router->name = 'Test Router';
    $router->host = '192.168.1.1';
    $router->api_port = 8729;
    $router->credential = 'plain_text_password';
    $router->sync_offset_minutes = 0;
    $router->status = 'HEALTHY';
    $router->save();

    $rawValue = DB::table('routers')
        ->where('id', $router->id)
        ->value('credential_encrypted');

    // Verify it's encrypted (not the original plain text)
    expect($rawValue)->not->toBe('plain_text_password');

    // Verify it can be decrypted back
    expect(Crypt::decryptString($rawValue))->toBe('plain_text_password');
});
