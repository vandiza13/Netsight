<?php

use App\Jobs\SyncRouterUsersJob;
use App\Jobs\WatchdogOrphanedSessionJob;
use App\Models\Router;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes & Scheduling
|--------------------------------------------------------------------------
*/

// ==========================================
// 1. Staggered Background Sync
// ==========================================
// Berjalan setiap menit, mendistribusikan beban query ke Mikrotik.
Schedule::command('app:cleanup-demo-sandboxes')->hourly();

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
        // Dispatch job ke queue 'default' (ditangani oleh Horizon)
        dispatch(new SyncRouterUsersJob($router));
    }
})->everyMinute()->name('staggered-sync')->withoutOverlapping();


// ==========================================
// 2. Torch Orphaned Session Watchdog
// ==========================================
// Laravel scheduler berjalan paling cepat 1 menit sekali.
// Karena kita butuh interval 15 detik, kita akan dispatch multiple job.
Schedule::call(function () {
    $interval = config('netsight.torch.watchdog_interval_seconds', 15);
    $runs = max(1, floor(60 / $interval));
    
    for ($i = 0; $i < $runs; $i++) {
        dispatch(new WatchdogOrphanedSessionJob())
            ->delay(now()->addSeconds($i * $interval));
    }
})->everyMinute()->name('torch-watchdog');
