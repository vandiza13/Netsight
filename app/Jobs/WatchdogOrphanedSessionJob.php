<?php

namespace App\Jobs;

use App\Services\TorchGuardrailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * WatchdogOrphanedSessionJob — Cron independen tiap 15 detik.
 *
 * Mendeteksi sesi Torch yang HANYA tercatat RUNNING di database
 * TETAPI tidak memiliki heartbeat lock aktif di Redis.
 * Jika terdeteksi, watchdog akan mengirim /cancel paksa ke router.
 *
 * @see SRD.md Section 4 step 14
 * @see SECURITY.md Section 5
 */
class WatchdogOrphanedSessionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(TorchGuardrailService $guardrailService): void
    {
        Log::debug('Watchdog cron started.');

        $cleaned = $guardrailService->cleanOrphanedSessions();

        if ($cleaned > 0) {
            Log::info("Watchdog finished. Cleaned {$cleaned} orphaned session(s).");
        }
    }
}
