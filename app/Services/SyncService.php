<?php

namespace App\Services;

use App\Models\PppoeUserCache;
use App\Models\Router;
use Illuminate\Support\Facades\Log;

/**
 * SyncService — Background sync logic untuk PPPoE users.
 *
 * Sinkronisasi data user PPPoE dari router MikroTik ke cache lokal.
 * Menggunakan staggered scheduling per router.
 *
 * @see SRD.md Section 1 — Background Sync
 * @see PRD.md Section 5 — Hybrid Cache Dashboard
 */
class SyncService
{
    public function __construct(
        private readonly MikrotikApiService $mikrotikApi
    ) {}

    /**
     * Sync user PPPoE dari router ke cache lokal.
     *
     * @see SRD.md Section 5 — Circuit breaker per router
     */
    public function syncRouter(Router $router): array
    {
        if ($router->isDegraded()) {
            Log::info("Router [{$router->name}] is DEGRADED, skipping sync.");

            return [
                'status' => 'SKIPPED',
                'reason' => 'Router is DEGRADED (circuit breaker active)',
            ];
        }

        try {
            $secrets = $this->mikrotikApi->getPppoeSecrets($router);
            
            // Ambil profile untuk fallback rate-limit
            $profiles = $this->mikrotikApi->getPppoeProfiles($router);
            $profileRateLimits = [];
            foreach ($profiles as $profile) {
                if (isset($profile['name']) && !empty($profile['rate-limit'])) {
                    $profileRateLimits[$profile['name']] = $profile['rate-limit'];
                }
            }

            $syncedCount = 0;
            $upsertData = [];
            $now = now()->toDateTimeString();

            foreach ($secrets as $secret) {
                $username = $secret['name'] ?? null;
                if (! $username) continue;

                $profileName = $secret['profile'] ?? null;
                
                // Prioritaskan rate-limit di secret, kalau kosong ambil dari profile
                $rawRateLimit = !empty($secret['rate-limit']) 
                    ? $secret['rate-limit'] 
                    : ($profileName && isset($profileRateLimits[$profileName]) ? $profileRateLimits[$profileName] : '');

                $upsertData[] = [
                    'router_id' => $router->id,
                    'username' => $username,
                    'profile' => $profileName,
                    'package_limit_mbps' => $this->extractRateLimit($rawRateLimit),
                    'is_active_last_check' => ! isset($secret['disabled']) || $secret['disabled'] === 'false',
                    'synced_at' => $now,
                ];
                $syncedCount++;
            }

            if (!empty($upsertData)) {
                // Chunk the upsert for safety (e.g., 500 per chunk)
                foreach (array_chunk($upsertData, 500) as $chunk) {
                    PppoeUserCache::upsert(
                        $chunk,
                        ['router_id', 'username'],
                        ['profile', 'package_limit_mbps', 'is_active_last_check', 'synced_at']
                    );
                }
            }

            // Ambil versi RouterOS
            $version = $this->mikrotikApi->getRouterVersion($router);

            // Reset failure counter on success
            $router->update([
                'status' => 'HEALTHY',
                'consecutive_sync_failures' => 0,
                'last_synced_at' => now(),
                'routeros_version' => $version ?: $router->routeros_version,
            ]);

            Log::info("Router [{$router->name}] synced successfully", [
                'synced_count' => $syncedCount,
            ]);

            return [
                'status' => 'SUCCESS',
                'synced_count' => $syncedCount,
            ];
        } catch (\Exception $e) {
            return $this->handleSyncFailure($router, $e);
        }
    }

    /**
     * Handle sync failure — increment counter, check circuit breaker.
     *
     * @see SRD.md Section 5 — 3x gagal berturut → DEGRADED, cooldown 15 menit
     */
    private function handleSyncFailure(Router $router, \Exception $e): array
    {
        $failures = $router->consecutive_sync_failures + 1;
        $threshold = config('netsight.sync.circuit_breaker_threshold');

        $newStatus = $failures >= $threshold ? 'DEGRADED' : $router->status;

        $router->update([
            'consecutive_sync_failures' => $failures,
            'status' => $newStatus,
        ]);

        Log::warning("Router [{$router->name}] sync failed ({$failures}/{$threshold})", [
            'error' => $e->getMessage(),
            'new_status' => $newStatus,
        ]);

        if ($newStatus === 'DEGRADED') {
            Log::error("Router [{$router->name}] marked DEGRADED after {$failures} consecutive failures");
        }

        return [
            'status' => 'FAILED',
            'failures' => $failures,
            'router_status' => $newStatus,
            'error' => $e->getMessage(),
        ];
    }

    /**
     * Ekstrak rate limit dari format MikroTik.
     * Format: "5M/5M" atau "10M" atau kosong.
     */
    private function extractRateLimit(string $rateLimit): ?int
    {
        if (empty($rateLimit)) {
            return null;
        }

        // Ambil download rate (sebelum /)
        $parts = explode('/', $rateLimit);
        $download = $parts[0] ?? $rateLimit;

        // Parse angka dan unit
        if (preg_match('/^(\d+)([MmKk]?)$/', trim($download), $matches)) {
            $value = (int) $matches[1];
            $unit = strtoupper($matches[2] ?? 'M');

            return match ($unit) {
                'M' => $value,
                'K' => (int) ceil($value / 1024),
                default => $value,
            };
        }

        return null;
    }
}
