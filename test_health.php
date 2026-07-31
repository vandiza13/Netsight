<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Vandiza\NetsightCore\Models\Router;
use Vandiza\NetsightCore\Services\SnmpHealthService;
use Illuminate\Support\Facades\Cache;

$router = Router::first();
echo "Router: " . $router->name . "\n";
echo "Host: " . $router->host . "\n";
echo "Community: " . $router->snmp_community . "\n";

$service = new SnmpHealthService();
$health = $service->getHealth($router);

echo "Health Data from Service:\n";
print_r($health);

echo "\nHealth Data from Cache:\n";
print_r(Cache::get('router_health_' . $router->id));
