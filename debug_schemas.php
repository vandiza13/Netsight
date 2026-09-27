<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$schemas = Illuminate\Support\Facades\DB::select("SELECT schema_name FROM information_schema.schemata WHERE schema_name LIKE 'demo_%' OR schema_name = 'public' OR schema_name = 'tenant_1'");

foreach ($schemas as $row) {
    $schema = $row->schema_name;
    Illuminate\Support\Facades\DB::statement("SET search_path TO {$schema}, public");
    $olts = \Vandiza\NetsightCore\Models\Olt::pluck('status')->unique()->values()->toArray();
    if (!empty($olts)) {
        echo "OLT statuses in {$schema}: " . json_encode($olts) . "\n";
    }
}
