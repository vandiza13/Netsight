<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $response = Illuminate\Support\Facades\Http::withHeaders(['x-api-key' => env('GENIEACS_NBI_SECRET', 'netsight_genieacs_secret')])
        ->post("http://10.99.99.1:7557/devices/413434-GM220%2DS-ZICG298DF6EA/tasks", [
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
