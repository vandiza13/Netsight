<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $s = app(\Vandiza\NetsightCore\Services\GenieAcsService::class);
    echo "Ensuring Fast Inform Preset...\n";
    $presetResult = $s->ensureFastInformPreset();
    echo "Preset result: " . ($presetResult ? "Success" : "Failed") . "\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
