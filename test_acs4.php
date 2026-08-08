<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$s = app(\Vandiza\NetsightCore\Services\GenieAcsService::class);
try {
    // 413434-GM220%2DS-ZICG298DF6EA -> GORIN_Faris, IP 10.120.2.147
    $response = Illuminate\Support\Facades\Http::withHeaders(['x-api-key' => env('GENIEACS_NBI_SECRET', 'netsight_genieacs_secret')])
        ->post("http://10.99.99.1:7557/devices/413434-GM220%2DS-ZICG298DF6EA/tasks?connection_request", [
        'name' => 'setParameterValues',
        'parameterValues' => [
            ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID', 'TestTest', 'xsd:string']
        ]
    ]);
    echo "Status: " . $response->status() . "\n";
    echo "Body: " . $response->body() . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
