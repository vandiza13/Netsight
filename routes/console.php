<?php

use Vandiza\NetsightCore\Jobs\SyncRouterUsersJob;
use Vandiza\NetsightCore\Jobs\WatchdogOrphanedSessionJob;
use Vandiza\NetsightCore\Models\Router;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes & Scheduling
|--------------------------------------------------------------------------
*/

// ==========================================
// 1. Demo Sandbox Commands (Non-Production Only)
// ==========================================
if (app()->environment('local', 'demo')) {
    Schedule::command('app:cleanup-demo-sandboxes')->hourly();
    Schedule::command('app:warm-demo-sandboxes')->everyMinute()->withoutOverlapping();
}

// ==========================================
// 2. Staggered Background Sync
// ==========================================
// Berjalan setiap menit, mendistribusikan beban query ke Mikrotik.
Schedule::call(function () {
    $currentMinute = now()->minute;

    // Ambil router yang offset-nya cocok dengan menit saat ini
    $routers = Router::where('sync_offset_minutes', $currentMinute)
        ->get();

    if ($routers->isEmpty()) {
        return;
    }

    Log::debug("Dispatching sync jobs for {$routers->count()} routers at minute {$currentMinute}");

    foreach ($routers as $router) {
        dispatch(new SyncRouterUsersJob($router));
    }
})->everyMinute()->name('staggered-sync')->withoutOverlapping();


// ==========================================
// 3. Torch Orphaned Session Watchdog
// ==========================================
Schedule::call(function () {
    $interval = config('netsight.torch.watchdog_interval_seconds', 15);
    $runs = max(1, floor(60 / $interval));

    for ($i = 0; $i < $runs; $i++) {
        dispatch(new WatchdogOrphanedSessionJob())
            ->delay(now()->addSeconds($i * $interval));
    }
})->everyMinute()->name('torch-watchdog');
// ==========================================
// 4. SNMP Live Traffic Poller
// ==========================================
Schedule::call(function () {
    $routers = Router::whereNotNull('monitored_interface')
        ->whereNotNull('snmp_community')
        ->get();

    if ($routers->isEmpty()) {
        return;
    }

    $interval = 10; // Poll every 10 seconds
    $runs = max(1, floor(60 / $interval));

    for ($i = 0; $i < $runs; $i++) {
        foreach ($routers as $router) {
            dispatch(new \Vandiza\NetsightCore\Jobs\PollRouterSnmpJob($router))
                ->delay(now()->addSeconds($i * $interval));
        }
    }
})->everyMinute()->name('snmp-poller');
