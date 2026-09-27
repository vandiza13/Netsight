<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$schema = 'public';
Illuminate\Support\Facades\DB::statement("SET search_path TO {$schema}");

echo "=== NETWORK NODES ===\n";
echo json_encode(\Vandiza\NetsightCore\Models\NetworkNode::all(), JSON_PRETTY_PRINT) . "\n";
