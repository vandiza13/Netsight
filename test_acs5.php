<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$s = app(\Vandiza\NetsightCore\Services\GenieAcsService::class);
try {
    $device = $s->getDevice('413434-GM220%2DS-ZICG298DF6EA');
    $deviceData = $device[0] ?? [];
    echo json_encode([
        'IP' => $deviceData['InternetGatewayDevice']['WANDevice']['1']['WANConnectionDevice']['1']['WANPPPConnection']['1']['ExternalIPAddress']['_value'] ?? 'N/A',
        'ConnectionRequestURL' => $deviceData['InternetGatewayDevice']['ManagementServer']['ConnectionRequestURL']['_value'] ?? 'N/A'
    ], JSON_PRETTY_PRINT);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
