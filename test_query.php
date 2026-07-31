<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = new \Vandiza\NetsightCore\Services\OltSnmpService();
$ip = '10.99.99.2';
$port = 1161;
$community = 'public';

$status = $service->walkOidRaw($ip, $port, $community, '1.3.6.1.4.1.25355.3.2.6.3.2.1.39');
$mac = $service->walkOidRaw($ip, $port, $community, '1.3.6.1.4.1.25355.3.2.6.3.2.1.11');
$desc = $service->walkOidRaw($ip, $port, $community, '1.3.6.1.4.1.25355.3.2.6.3.2.1.37');
$rx = $service->walkOidRaw($ip, $port, $community, '1.3.6.1.4.1.25355.3.2.6.14.2.1.8');

echo 'Status Count: ' . count($status) . PHP_EOL;
echo 'MAC Count: ' . count($mac) . PHP_EOL;
echo 'Desc Count: ' . count($desc) . PHP_EOL;
echo 'RX Count: ' . count($rx) . PHP_EOL;

