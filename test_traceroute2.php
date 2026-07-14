<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$router = App\Models\Router::first(); 
$api = app(App\Services\MikrotikApiService::class); 

$ip = "10.120.2.176";
echo "Running traceroute to {$ip}...\n";

$hops = $api->tracerouteUser($router, $ip);
$hops = $api->tracerouteUser($router, $ip);
echo "Result:\n";
print_r($hops);

