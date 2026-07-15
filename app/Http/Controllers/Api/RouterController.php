<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PppoeUserCache;
use App\Models\Router;
use App\Services\MikrotikApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * RouterController — Endpoint untuk manajemen dan monitoring router.
 *
 * @see SRD.md Section 3
 */
class RouterController extends Controller
{
    public function __construct(
        private readonly MikrotikApiService $mikrotikApi
    ) {}

    /**
     * GET /api/routers — Daftar router + status.
     *
     * @role TIER_1+
     */
    public function index(): JsonResponse
    {
        $routers = Router::select([
            'id', 'name', 'host', 'api_user', 'api_port',
            'routeros_version', 'status',
            'last_synced_at', 'consecutive_sync_failures',
        ])->get();

        return response()->json([
            'data' => $routers,
        ]);
    }

    /**
     * GET /api/routers/{id}/users — Cache user PPPoE dari database lokal.
     *
     * Query dari database lokal, BUKAN dari router langsung.
     * @see AGENT.md Section 0 rule 2 — Jangan menembak API MikroTik untuk data yang bisa dari cache
     * @see SRD.md Section 5 — Response < 50ms untuk 5.000 baris
     *
     * @role TIER_1+
     */
    public function users(Request $request, int $id): JsonResponse
    {
        $router = Router::findOrFail($id);

        $query = PppoeUserCache::where('router_id', $router->id);

        // Search filter
        if ($search = $request->query('search')) {
            // Sanitize search input
            $search = preg_replace('/[^a-zA-Z0-9._\-@]/', '', $search);
            $query->where('username', 'ILIKE', "%{$search}%");
        }

        $users = $query
            ->select(['id', 'username', 'profile', 'package_limit_mbps', 'is_active_last_check', 'synced_at'])
            ->orderBy('username')
            ->paginate(50);

        return response()->json($users);
    }

    /**
     * POST /api/routers/{id}/force-sync — Trigger sync manual.
     *
     * Rate limited: 1x per 5 menit per router.
     * @see SRD.md Section 5 — Rate limit force-sync
     *
     * @role TIER_2+
     */
    public function forceSync(Request $request, int $id): JsonResponse
    {
        if ($request->header('X-Demo-Schema')) {
            return response()->json([
                'message' => 'Sync manual selesai secara instan (Mode Simulasi Demo).',
                'router' => 'Demo Router',
            ]);
        }

        $router = Router::findOrFail($id);

        // Rate limit: 1x per 5 menit per router
        $rateLimitKey = "force-sync:router:{$router->id}";
        $rateLimitMinutes = config('netsight.sync.force_sync_rate_limit_minutes');

        if (RateLimiter::tooManyAttempts($rateLimitKey, 1)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $minutes = ceil($seconds / 60);

            return response()->json([
                'message' => "Terlalu sering, tunggu {$minutes} menit lagi.",
                'retry_after_seconds' => $seconds,
            ], 429);
        }

        // Cek apakah router dalam status DEGRADED
        if ($router->isDegraded()) {
            return response()->json([
                'message' => 'Router dalam status DEGRADED. Sync ditunda hingga cooldown selesai.',
                'status' => $router->status,
            ], 503);
        }

        RateLimiter::hit($rateLimitKey, $rateLimitMinutes * 60);

        // Dispatch sync job (ditangani oleh Horizon)
        \App\Jobs\SyncRouterUsersJob::dispatch($router);

        return response()->json([
            'message' => 'Sync manual dijadwalkan.',
            'router' => $router->name,
        ]);
    }

    /**
     * GET /api/routers/{id}/health-check — Trigger /system resource print.
     *
     * Pre-flight CPU check sebelum Torch.
     * @see SECURITY.md Section 4.3
     *
     * @role TIER_2+
     */
    public function healthCheck(Request $request, int $id): JsonResponse
    {
        if ($request->header('X-Demo-Schema')) {
            return response()->json([
                'status' => 'OK',
                'cpu_load' => rand(3, 12),
                'free_memory' => 842000000,
                'total_memory' => 1024000000,
                'uptime' => '10d 5h 30m',
                'version' => '7.12 (Demo)',
                'thresholds' => ['warning' => 80, 'critical' => 95],
            ]);
        }

        $router = Router::findOrFail($id);

        try {
            $resource = $this->mikrotikApi->getSystemResource($router);
            $cpuThreshold = config('netsight.torch.cpu_threshold');
            $cpuWarning = config('netsight.torch.cpu_warning');

            $status = 'OK';

            if ($resource['cpu_load'] > $cpuThreshold) {
                $status = 'CRITICAL';
            } elseif ($resource['cpu_load'] > $cpuWarning) {
                $status = 'WARNING';
            }

            return response()->json([
                'status' => $status,
                'cpu_load' => $resource['cpu_load'],
                'free_memory' => $resource['free_memory'],
                'total_memory' => $resource['total_memory'],
                'uptime' => $resource['uptime'],
                'version' => $resource['version'],
                'thresholds' => [
                    'warning' => $cpuWarning,
                    'critical' => $cpuThreshold,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'UNREACHABLE',
                'message' => 'Router tidak dapat dihubungi.',
            ], 503);
        }
    }
    /**
     * POST /api/routers — Create a new router.
     *
     * @role ADMIN
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'api_user' => 'nullable|string|max:50',
            'api_port' => 'required|integer|min:1|max:65535',
            'credential' => 'required|string',
            'sync_offset_minutes' => 'nullable|integer|min:1',
        ]);

        $demoSchema = $request->header('X-Demo-Schema');
        if ($demoSchema) {
            return response()->json([
                'message' => 'Penambahan router dinonaktifkan di mode demo. Silakan gunakan Dummy Router yang sudah tersedia.',
            ], 403);
        }

        $router = new Router();
        $router->name = $validated['name'];
        $router->host = $validated['host'];
        $router->api_user = $validated['api_user'] ?? 'admin';
        $router->api_port = $validated['api_port'];
        $router->credential = $validated['credential'];
        if (isset($validated['sync_offset_minutes'])) {
            $router->sync_offset_minutes = $validated['sync_offset_minutes'];
        }
        $router->status = 'HEALTHY'; // Default status
        $router->save();

        return response()->json([
            'message' => 'Router created successfully.',
            'data' => $router,
        ], 201);
    }

    /**
     * PUT /api/routers/{id} — Update an existing router.
     *
     * @role ADMIN
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if ($request->header('X-Demo-Schema')) {
            return response()->json([
                'message' => 'Pengubahan router dinonaktifkan di mode demo.',
            ], 403);
        }

        $router = Router::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'host' => 'sometimes|required|string|max:255',
            'api_user' => 'nullable|string|max:50',
            'api_port' => 'sometimes|required|integer|min:1|max:65535',
            'credential' => 'nullable|string',
            'sync_offset_minutes' => 'nullable|integer|min:1',
        ]);


        if (isset($validated['name'])) $router->name = $validated['name'];
        if (isset($validated['host'])) $router->host = $validated['host'];
        if (isset($validated['api_user'])) $router->api_user = $validated['api_user'];
        if (isset($validated['api_port'])) $router->api_port = $validated['api_port'];
        if (!empty($validated['credential'])) {
            $router->credential = $validated['credential'];
        }
        if (isset($validated['sync_offset_minutes'])) {
            $router->sync_offset_minutes = $validated['sync_offset_minutes'];
        }
        $router->save();

        return response()->json([
            'message' => 'Router updated successfully.',
            'data' => $router,
        ]);
    }

    /**
     * DELETE /api/routers/{id} — Delete a router.
     *
     * @role ADMIN
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($request->header('X-Demo-Schema')) {
            return response()->json([
                'message' => 'Penghapusan router dinonaktifkan di mode demo.',
            ], 403);
        }

        $router = Router::findOrFail($id);
        
        // Ensure cascading deletion or handle relationships if necessary
        // In this case, DB foreign keys should have cascading deletes (e.g. pppoe_user_caches)
        $router->delete();

        return response()->json([
            'message' => 'Router deleted successfully.',
        ]);
    }
}
