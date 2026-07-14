<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Router;
use App\Services\MikrotikApiService;

$router = Router::first();
echo "Testing router ID: {$router->id}, Host: {$router->host}, Port: {$router->api_port}, User: {$router->api_user}\n";

$api = app(MikrotikApiService::class);
try {
    echo "Connecting...\n";
    $secrets = $api->getPppoeSecrets($router);
    echo "Success! Found " . count($secrets) . " secrets.\n";
} catch (\Exception $e) {
    echo "Failed! Error: " . $e->getMessage() . "\n";
}
