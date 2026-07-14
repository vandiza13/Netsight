<?php

namespace App\Jobs;

use App\Models\Router;
use App\Services\SyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * SyncRouterUsersJob — Background sync user PPPoE per router.
 *
 * Dijalankan via Laravel Horizon (Redis-backed queue).
 * Staggered scheduling berdasarkan sync_offset_minutes.
 *
 * @see SRD.md Section 1 — Background Sync
 * @see AGENT.md Fase 2 — Background Sync & Cache Layer
 */
class SyncRouterUsersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Timeout job sesuai connection timeout + buffer.
     */
    public int $timeout = 30;

    /**
     * Maksimal retry = config retry + 1.
     */
    public int $tries = 1;

    public function __construct(
        public readonly Router $router
    ) {}

    public function handle(SyncService $syncService): void
    {
        $syncService->syncRouter($this->router);
    }
}
