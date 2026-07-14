<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$router = App\Models\Router::first();
$sync = app(App\Services\SyncService::class);
$sync->syncRouter($router);
echo "Synced router " . $router->name . "\n";
$router->refresh();
echo "Version: " . $router->routeros_version . "\n";
