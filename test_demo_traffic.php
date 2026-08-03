<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$schema = \Illuminate\Support\Facades\DB::table('public.demo_sandboxes')->where('status', 'idle')->value('schema_name');
echo "Schema: $schema\n";
config(['database.connections.pgsql.search_path' => $schema]);
\Illuminate\Support\Facades\DB::purge('pgsql');
$req = \Illuminate\Http\Request::create('/api/traffic/dashboard', 'GET');
$req->headers->set('X-Demo-Schema', $schema);
$ctrl = app(\Vandiza\NetsightCore\Http\Controllers\Api\RouterController::class);
$resp = $ctrl->dashboardTraffic($req);
echo $resp->getContent();
