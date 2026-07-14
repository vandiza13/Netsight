<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Router;

$routers = Router::all();
foreach ($routers as $r) {
    echo "ID: {$r->id} | Name: {$r->name} | Host: {$r->host} | API_USER: {$r->api_user} | Port: {$r->api_port}\n";
}
