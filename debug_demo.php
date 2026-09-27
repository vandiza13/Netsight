<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$schemas = Illuminate\Support\Facades\DB::select("SELECT schema_name FROM information_schema.schemata WHERE schema_name LIKE 'demo_%'");
foreach ($schemas as $s) {
    $schema = $s->schema_name;
    Illuminate\Support\Facades\DB::statement("SET search_path TO {$schema}, public");
    echo "{$schema} Routers: " . json_encode(\Vandiza\NetsightCore\Models\Router::pluck('name')) . "\n";
    echo "{$schema} Nodes: " . json_encode(\Vandiza\NetsightCore\Models\NetworkNode::pluck('name')) . "\n";
}
