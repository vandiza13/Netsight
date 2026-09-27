<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
Illuminate\Support\Facades\DB::statement("SET search_path TO " . env('DB_SCHEMA','public') . ", public");

echo "Router status values: " . json_encode(\Vandiza\NetsightCore\Models\Router::pluck('status')->toArray()) . "\n";
echo "OLT status values: " . json_encode(\Vandiza\NetsightCore\Models\Olt::pluck('status')->toArray()) . "\n";
echo "ACS status values: " . json_encode(\Vandiza\NetsightCore\Models\AcsDevice::pluck('status')->unique()->values()->toArray()) . "\n";
