<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Check both schemas just in case
$schemas = ['public', 'tenant_1'];
foreach ($schemas as $schema) {
    try {
        Illuminate\Support\Facades\DB::statement("SET search_path TO {$schema}, public");
        $olts = \Vandiza\NetsightCore\Models\Olt::pluck('status')->unique()->values()->toArray();
        echo "OLT statuses in {$schema}: " . json_encode($olts) . "\n";
    } catch (\Exception $e) {
        echo "Error in {$schema}: " . $e->getMessage() . "\n";
    }
}
