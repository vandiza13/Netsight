<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Vandiza\NetsightCore\Models\Router;
use Vandiza\NetsightCore\Services\MikrotikApiService;
use Vandiza\NetsightCore\Services\SyncService;

$router = Router::first();
if (!$router) die("No router\n");

$api = app(MikrotikApiService::class);
try {
    $active = $api->getActivePppoeSession($router);
    echo "Total Active: " . count($active) . "\n";
    if (count($active) > 0) {
        var_dump($active[0]);
    }
    
    $queues = $api->execute($router, '/queue/simple/print');
    echo "Total Queues: " . count($queues) . "\n";
    if (count($queues) > 0) {
        var_dump($queues[0]);
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
