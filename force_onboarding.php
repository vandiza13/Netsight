<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Vandiza\NetsightCore\Models\StaffNoc;

$admin = StaffNoc::where('email', 'admin@netsight.xyz')->first();
if ($admin) {
    $admin->must_change_password = true;
    $admin->save();
    echo "must_change_password has been set to true for admin@netsight.xyz\n";
} else {
    echo "Admin not found.\n";
}
