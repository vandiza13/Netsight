<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$sandboxes = \Illuminate\Support\Facades\DB::table('public.demo_sandboxes')->get();
foreach ($sandboxes as $sb) {
    echo "Dropping schema {$sb->schema_name}\n";
    \Illuminate\Support\Facades\DB::statement("DROP SCHEMA IF EXISTS {$sb->schema_name} CASCADE");
}
\Illuminate\Support\Facades\DB::table('public.demo_sandboxes')->truncate();
echo "Done truncating.\n";
