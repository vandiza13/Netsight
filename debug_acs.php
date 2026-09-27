<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$schema = 'tenant_1'; // The demo schema
Illuminate\Support\Facades\DB::statement("SET search_path TO {$schema}, public");

echo "ACS status values: " . json_encode(\Vandiza\NetsightCore\Models\AcsDevice::pluck('status')->unique()->values()->toArray()) . "\n";
