<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Router;
use App\Models\TorchSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * TorchGuardrailService — Heartbeat lock, circuit breaker, pre-flight check.
 *
 * Mengelola semua guardrail yang melindungi router produksi dari overload
 * saat sesi Torch dijalankan.
 *
 * @see SRD.md Section 4 — Alur Sekuensial Torch Engine
 * @see SECURITY.md Section 5 — Guardrail Operasional
 */
class TorchGuardrailService
{
    public function __construct(
        private readonly MikrotikApiService $mikrotikApi
    ) {}

    /**
     * Pre-flight CPU check.
     *
     * @see SRD.md Section 4 step 2
     * @see SECURITY.md Section 4.3
     *
     * @return array{allowed: bool, cpu_load: int, message: string}
     */
    public function preFlight(Router $router): array
    {
        $resource = $this->mikrotikApi->getSystemResource($router);
        $cpuLoad = $resource['cpu_load'];
        $threshold = config('netsight.torch.cpu_threshold');
        $warning = config('netsight.torch.cpu_warning');

        if ($cpuLoad > $threshold) {
            return [
                'allowed' => false,
                'cpu_load' => $cpuLoad,
                'message' => "Router CPU tinggi ({$cpuLoad}%), Torch ditunda.",
            ];
        }

        $warningMessage = null;
        if ($cpuLoad > $warning) {
            $warningMessage = "Router CPU moderate ({$cpuLoad}%). Melanjutkan dengan peringatan.";
        }

        return [
            'allowed' => true,
            'cpu_load' => $cpuLoad,
            'message' => $warningMessage ?? 'CPU OK.',
        ];
    }

    /**
     * Cek concurrency lock — maksimal N sesi per router.
     *
     * @see SRD.md Section 4 step 3
     * @see SECURITY.md Section 5
     */
    public function checkConcurrency(Router $router): array
    {
        // 1. Cek concurrency lokal per-router di schema saat ini
        $maxConcurrent = config('netsight.torch.max_concurrent_per_router');
        $activeSessions = TorchSession::where('router_id', $router->id)
            ->running()
            ->count();

        if ($activeSessions >= $maxConcurrent) {
            return [
                'allowed' => false,
                'message' => 'Router sedang mencapai batas maksimal sesi Torch paralel.',
            ];
        }

        // 2. Cek concurrency GLOBAL (lintas schema/demo)
        // Batasi maksimal sesi Torch aktif secara global di server demo
        $maxGlobalConcurrent = config('netsight.torch.max_global_concurrent', 5); 
        $activeGlobalTags = Cache::get('torch:global_active_sessions', []);

        // Saring tag yang detak jantungnya (heartbeat) sudah mati untuk menghindari deadlocks
        $validGlobalTags = [];
        foreach ($activeGlobalTags as $tag) {
            if ($this->isHeartbeatAlive($tag)) {
                $validGlobalTags[] = $tag;
            }
        }

        // Simpan kembali list yang valid
        Cache::put('torch:global_active_sessions', $validGlobalTags, 3600);

        if (count($validGlobalTags) >= $maxGlobalConcurrent) {
            Log::warning('Global Torch concurrency limit reached', [
                'active_tags' => $validGlobalTags,
                'limit' => $maxGlobalConcurrent
            ]);
            return [
                'allowed' => false,
                'message' => "Batas maksimal ({$maxGlobalConcurrent}) sesi Torch aktif secara bersamaan di server DEMO tercapai. Sesi dibatasi secara global demi stabilitas API Router. Silakan coba kembali dalam 10-15 detik.",
            ];
        }

        return [
            'allowed' => true,
            'message' => 'Concurrency OK.',
        ];
    }

    /**
     * Acquire heartbeat lock di Cache.
     *
     * @see SRD.md Section 4 step 6
     * @param string $sessionTag Tag unik sesi Torch
     */
    public function acquireHeartbeatLock(string $sessionTag): bool
    {
        $ttl = config('netsight.torch.heartbeat_ttl_seconds');
        $lockKey = "torch:heartbeat:{$sessionTag}";

        if (Cache::add($lockKey, now()->timestamp, $ttl)) {
            // Daftarkan ke list global secara aman (Atomic Lock)
            $lock = Cache::lock('torch:global_list_lock', 5);
            $lock->get(function () use ($sessionTag) {
                $activeGlobalTags = Cache::get('torch:global_active_sessions', []);
                $activeGlobalTags[] = $sessionTag;
                Cache::put('torch:global_active_sessions', array_unique($activeGlobalTags), 3600);
            });
            return true;
        }

        return false;
    }

    /**
     * Refresh heartbeat lock TTL.
     *
     * @see SRD.md Section 4 step 10
     */
    public function refreshHeartbeat(string $sessionTag): bool
    {
        $ttl = config('netsight.torch.heartbeat_ttl_seconds');
        $lockKey = "torch:heartbeat:{$sessionTag}";

        return Cache::put($lockKey, now()->timestamp, $ttl);
    }

    /**
     * Release heartbeat lock.
     */
    public function releaseHeartbeat(string $sessionTag): void
    {
        $lockKey = "torch:heartbeat:{$sessionTag}";
        Cache::forget($lockKey);

        // Hapus dari list global secara aman (Atomic Lock)
        $lock = Cache::lock('torch:global_list_lock', 5);
        $lock->get(function () use ($sessionTag) {
            $activeGlobalTags = Cache::get('torch:global_active_sessions', []);
            $activeGlobalTags = array_diff($activeGlobalTags, [$sessionTag]);
            Cache::put('torch:global_active_sessions', array_values($activeGlobalTags), 3600);
        });
    }

    /**
     * Cek apakah heartbeat lock masih hidup.
     * Digunakan oleh watchdog untuk deteksi orphaned session.
     *
     * @see SRD.md Section 4 step 14
     */
    public function isHeartbeatAlive(string $sessionTag): bool
    {
        $lockKey = "torch:heartbeat:{$sessionTag}";

        return Cache::has($lockKey);
    }

    /**
     * Watchdog: deteksi dan bersihkan orphaned sessions.
     *
     * Sesi RUNNING di DB tanpa heartbeat lock hidup = orphaned.
     * Kirim /cancel paksa, update status FORCE_TERMINATED.
     *
     * @see SRD.md Section 4 step 14
     * @see SECURITY.md Section 6 — auto_cleanup harus terpisah dari pembatalan manual
     */
    public function cleanOrphanedSessions(): int
    {
        $orphanedSessions = TorchSession::running()
            ->with('router')
            ->get();

        $cleanedCount = 0;

        foreach ($orphanedSessions as $session) {
            if (! $this->isHeartbeatAlive($session->tag)) {
                Log::warning('Orphaned Torch session detected', [
                    'session_id' => $session->id,
                    'tag' => $session->tag,
                    'router' => $session->router->name ?? 'unknown',
                    'username' => $session->username,
                ]);

                try {
                    // Kirim /cancel paksa ke router
                    if ($session->router) {
                        $this->mikrotikApi->cancelTorch($session->router, $session->tag);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to cancel orphaned Torch session on router', [
                        'session_id' => $session->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                // Update status di database
                $session->update([
                    'status' => 'FORCE_TERMINATED',
                    'auto_cleanup' => true, // Tandai sebagai auto-cleanup
                    'ended_at' => now(),
                ]);

                $cleanedCount++;
            }
        }

        if ($cleanedCount > 0) {
            Log::info("Watchdog cleaned {$cleanedCount} orphaned Torch session(s).");
        }

        return $cleanedCount;
    }

    /**
     * Generate tag unik untuk sesi Torch.
     */
    public function generateTag(): string
    {
        return 'torch-' . bin2hex(random_bytes(8));
    }
}
