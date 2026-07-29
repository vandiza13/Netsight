<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Vandiza\NetsightCore\Models\Router;
use Vandiza\NetsightCore\Services\SnmpTrafficService;
use Illuminate\Support\Facades\Cache;

$router = Router::first();
echo "Router: " . $router->name . "\n";
echo "Interface: " . $router->monitored_interface . "\n";

$service = app(SnmpTrafficService::class);

echo "\n--- Traffic Dump 1 ---\n";
$t1 = $service->getTraffic($router);
var_dump($t1);

echo "\nWaiting 5 seconds...\n";
sleep(5);

echo "\n--- Traffic Dump 2 ---\n";
$t2 = $service->getTraffic($router);
var_dump($t2);
