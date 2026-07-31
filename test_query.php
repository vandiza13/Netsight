<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$r = \Vandiza\NetsightCore\Models\Router::first();
$r->total_pppoe_count = 50;
echo json_encode($r);

