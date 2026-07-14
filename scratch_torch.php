<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Router;
use App\Services\MikrotikApiService;
use RouterOS\Client;
use RouterOS\Query;

$api = app(MikrotikApiService::class);
$router = Router::where('name', 'BimaNet')->first();

try {
    echo "Starting Torch on BimaNet...\n";
    $client = $api->connect($router);
    
    $interface = '<pppoe-CBNT_Indra_Lesmana>'; // You can change this if needed
    echo "Using interface: $interface\n";
    
    $query = (new Query('/tool/torch'))
        ->equal('interface', $interface)
        ->equal('src-address', '0.0.0.0/0')
        ->equal('dst-address', '0.0.0.0/0')
        ->equal('port', 'any')
        ->equal('duration', '3')
        ->tag('test-123');
        
    $client->query($query);
    
    echo "Query sent. Waiting for packets...\n";
    $response = $client->read();
    print_r($response);
} catch (\Throwable $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
} finally {
    try { $api->cancelTorch($router, 'test-123'); } catch (\Exception $e) {}
}
