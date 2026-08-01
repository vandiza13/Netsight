<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$olt = \Vandiza\NetsightCore\Models\Olt::first();
$api = app(\Vandiza\NetsightCore\Services\OltSnmpService::class);

echo "STATUS:\n";
$status = $api->walkOidRaw($olt->ip_address, $olt->snmp_port ?: 161, $olt->snmp_community, '1.3.6.1.4.1.17409.2.3.4.1.1.8');
print_r(array_slice($status, -5, 5, true));

echo "\nMAC:\n";
$mac = $api->walkOidRaw($olt->ip_address, $olt->snmp_port ?: 161, $olt->snmp_community, '1.3.6.1.4.1.17409.2.3.4.1.1.2');
print_r(array_slice($mac, -5, 5, true));

echo "\nRX:\n";
$rx = $api->walkOidRaw($olt->ip_address, $olt->snmp_port ?: 161, $olt->snmp_community, '1.3.6.1.4.1.17409.2.3.4.2.1.4');
print_r(array_slice($rx, -5, 5, true));
