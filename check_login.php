<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Vandiza\NetsightCore\Models\StaffNoc;
use Illuminate\Support\Facades\Hash;

$staff = StaffNoc::where('email', 'admin@netsight.xyz')->first();

echo "Staff: " . ($staff ? 'Found' : 'Not Found') . "\n";
if ($staff) {
    echo "Is Active: " . ($staff->is_active ? 'Yes' : 'No') . "\n";
    echo "Hash Check (admin): " . (Hash::check('admin', $staff->password_hash) ? 'Passed' : 'Failed') . "\n";
}
