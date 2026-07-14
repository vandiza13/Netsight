<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Router;
use App\Models\TorchSession;
use App\Services\MikrotikApiService;
use App\Services\TorchGuardrailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * TorchController — Inisiasi, Manajemen, dan Pembatalan Sesi Torch.
 *
 * @see SRD.md Section 4
 */
class TorchController extends Controller
{
    public function __construct(
        private readonly MikrotikApiService $mikrotikApi,
        private readonly TorchGuardrailService $guardrailService
    ) {}

    /**
     * POST /api/torch/inspect — Mulai sesi Torch (langkah 1-7 di SRD.md).
     *
     * @role TIER_2+
     */
    public function inspect(Request $request): JsonResponse
    {
        $request->validate([
            'router_id' => 'required|exists:routers,id',
            'username' => 'required|string|max:100',
        ]);

        $router = Router::findOrFail($request->router_id);
        $username = $request->username;

        // 1. Pre-flight CPU Check
        $cpuCheck = $this->guardrailService->preFlight($router);
        if (!$cpuCheck['allowed']) {
            return response()->json([
                'message' => $cpuCheck['message'],
                'cpu_load' => $cpuCheck['cpu_load'],
            ], 503);
        }

        // 2. Concurrency Check
        // Fallback cleanup: only delete RUNNING sessions older than 5 min (stuck/zombie)
        TorchSession::where('router_id', $router->id)
            ->where('status', 'RUNNING')
            ->where('started_at', '<', now()->subMinutes(5))
            ->update(['status' => 'TIMEOUT', 'ended_at' => now()]);

        $concurrencyCheck = $this->guardrailService->checkConcurrency($router);
        if (!$concurrencyCheck['allowed']) {
            return response()->json([
                'message' => $concurrencyCheck['message'],
            ], 429);
        }

        try {
            // 3. Validasi PPPoE aktif & ambil nama interface dinamis
            $interfaceName = $this->mikrotikApi->getActivePppoeInterface($router, $username);
            
            if (!$interfaceName) {
                return response()->json([
                    'message' => "User PPPoE '{$username}' tidak aktif di router ini.",
                ], 404);
            }

            // 4. Generate Tag & Acquire Heartbeat Lock
            $tag = $this->guardrailService->generateTag();
            
            if (!$this->guardrailService->acquireHeartbeatLock($tag)) {
                return response()->json([
                    'message' => 'Gagal mendapatkan heartbeat lock. Coba lagi.',
                ], 409);
            }

            // 5. Simpan ke database dengan status RUNNING
            $session = TorchSession::create([
                'router_id' => $router->id,
                'username' => $username,
                'dynamic_interface' => $interfaceName,
                'initiated_by' => $request->user()->id,
                'tag' => $tag,
                'status' => 'RUNNING',
                'started_at' => now(),
            ]);

            // Phase 5: Rekam Audit Log
            \App\Models\AuditLog::record(
                staffId: $request->user()->id,
                action: 'START_TORCH',
                targetUsername: $username,
                routerId: $router->id,
                metadata: [
                    'session_tag' => $tag,
                    'interface' => $interfaceName,
                    'cpu_load' => $cpuCheck['cpu_load'],
                ]
            );

            // 6. Ambil konfigurasi Queue pelanggan (Bandwidth Limit)
            $queueInfo = $this->mikrotikApi->getUserQueueLimit($router, $username);

            // 7. Return response, frontend akan open koneksi SSE ke stream()
            return response()->json([
                'session_tag' => $tag,
                'interface' => $interfaceName,
                'queue' => $queueInfo,
                'warnings' => $cpuCheck['cpu_load'] > 70 ? 'High CPU Load detected on router' : null
            ]);

        } catch (\Exception $e) {
            Log::error('Gagal inisiasi Torch', [
                'router_id' => $router->id,
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Gagal berkomunikasi dengan router: ' . $e->getMessage(),
            ], 502);
        }
    }

    /**
     * POST /api/torch/{tag}/heartbeat — Refresh heartbeat lock.
     *
     * @role TIER_2+
     */
    public function heartbeat(string $tag): JsonResponse
    {
        $session = TorchSession::where('tag', $tag)->firstOrFail();

        if ($session->status !== 'RUNNING') {
            return response()->json(['message' => 'Session is not RUNNING'], 400);
        }

        if ($this->guardrailService->refreshHeartbeat($tag)) {
            return response()->json(['status' => 'OK']);
        }

        return response()->json(['message' => 'Heartbeat lock lost'], 410);
    }

    /**
     * POST /api/torch/{tag}/cancel — Batalkan sesi Torch manual.
     *
     * @role TIER_2+
     */
    public function cancel(Request $request, string $tag): JsonResponse
    {
        $session = TorchSession::where('tag', $tag)->firstOrFail();

        if ($session->status !== 'RUNNING') {
            return response()->json(['message' => 'Sesi sudah tidak aktif.']);
        }

        try {
            // Cancel torch process in router
            $this->mikrotikApi->cancelTorch($session->router, $tag);
        } catch (\Exception $e) {
            Log::error('Gagal cancel torch di router', [
                'tag' => $tag,
                'error' => $e->getMessage()
            ]);
            // Tetap update database meski router gagal merespon
        }

        $session->update([
            'status' => 'CANCELLED',
            'ended_at' => now(),
        ]);
        
        $this->guardrailService->releaseHeartbeat($tag);

        // Phase 5: Rekam Audit Log
        \App\Models\AuditLog::record(
            staffId: $request->user()->id,
            action: 'CANCEL_TORCH',
            targetUsername: $session->username,
            routerId: $session->router_id,
            metadata: [
                'session_tag' => $tag,
            ]
        );

        return response()->json([
            'message' => 'Torch session cancelled.',
        ]);
    }

    /**
     * GET /api/torch/history — Daftar riwayat sesi Torch.
     */
    public function history(Request $request): JsonResponse
    {
        $query = TorchSession::with(['router:id,name', 'initiator:id,name'])
            ->where('status', '!=', 'RUNNING');

        if ($request->filled('username')) {
            $username = preg_replace('/[^a-zA-Z0-9._\-@]/', '', $request->username);
            $query->where('username', 'ILIKE', "%{$username}%");
        }

        if ($request->filled('router_id')) {
            $query->where('router_id', $request->router_id);
        }

        $history = $query->select([
            'id', 'router_id', 'username', 'initiated_by', 'status',
            'started_at', 'ended_at', 'peak_tx_bps', 'peak_rx_bps',
            'avg_tx_bps', 'avg_rx_bps', 'diagnostic_conclusion'
        ])
        ->orderBy('started_at', 'desc')
        ->paginate(15);

        return response()->json($history);
    }

    /**
     * GET /api/torch/history/{id} — Detail riwayat sesi Torch (termasuk grafik sampel).
     */
    public function show(int $id): JsonResponse
    {
        $session = TorchSession::with(['router:id,name', 'initiator:id,name'])
            ->where('status', '!=', 'RUNNING')
            ->findOrFail($id);

        return response()->json($session);
    }

    /**
     * DELETE /api/torch/history/{id} — Hapus riwayat sesi Torch (Admin only).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $session = TorchSession::findOrFail($id);

        // Hanya history yang sudah tidak jalan yang boleh dihapus
        if ($session->status === 'RUNNING') {
            return response()->json(['message' => 'Tidak bisa menghapus sesi yang sedang berjalan.'], 400);
        }

        $session->delete();

        // Phase 5: Rekam Audit Log
        \App\Models\AuditLog::record(
            staffId: $request->user()->id,
            action: 'DELETE_TORCH_HISTORY',
            targetUsername: $session->username,
            routerId: $session->router_id,
            metadata: [
                'session_id' => $id,
                'session_tag' => $session->tag,
            ]
        );

        return response()->json(['message' => 'Riwayat berhasil dihapus.']);
    }

    /**
     * GET /api/torch/{tag}/ping — Dapatkan stats ping untuk user yang sedang di-inspect.
     *
     * @role TIER_2+
     */
    public function ping(string $tag): JsonResponse
    {
        $session = TorchSession::where('tag', $tag)->with('router')->firstOrFail();

        // Cari IP aktif user
        $userIp = null;
        $activeSessions = $this->mikrotikApi->getActivePppoeSession($session->router, $session->username);
        if (!empty($activeSessions) && isset($activeSessions[0]['address'])) {
            $userIp = $activeSessions[0]['address'];
        }

        if (!$userIp) {
            return response()->json(['message' => 'IP Address user tidak ditemukan'], 404);
        }

        // Ping count=3 untuk dapatkan packet loss dan jitter-like info
        $pingResult = $this->mikrotikApi->pingUser($session->router, $userIp, 3);
        
        return response()->json(['data' => $pingResult]);
    }

    /**
     * Endpoint untuk mengambil data log (digunakan oleh SSE fallback atau manual fetch jika perlu)
     */
    public function logs(string $tag)
    {
        $session = TorchSession::where('tag', $tag)->firstOrFail();
        $router = Router::findOrFail($session->router_id);

        $logs = $this->mikrotikApi->getSystemLogs($router, 30);
        return response()->json(['data' => $logs]);
    }

    /**
     * Endpoint untuk menjalankan Traceroute ke IP pelanggan
     */
    public function traceroute(string $tag)
    {
        $session = TorchSession::where('tag', $tag)->first();
        
        if (!$session) {
            Log::error('Traceroute: session not found', ['tag' => $tag]);
            return response()->json(['message' => 'Session tidak ditemukan. Coba inspect ulang.'], 404);
        }

        $router = Router::find($session->router_id);
        if (!$router) {
            Log::error('Traceroute: router not found', ['router_id' => $session->router_id]);
            return response()->json(['message' => 'Router tidak ditemukan'], 404);
        }

        try {
            $ipAddress = $this->mikrotikApi->getPppoeIpAddress($router, $session->username);
            
            if (!$ipAddress) {
                Log::warning('Traceroute: IP not found for user', ['username' => $session->username]);
                return response()->json(['message' => 'User IP tidak ditemukan. User mungkin sudah disconnect.'], 404);
            }

            Log::info('Traceroute: starting', ['username' => $session->username, 'ip' => $ipAddress]);
            $hops = $this->mikrotikApi->tracerouteUser($router, $ipAddress);
            
            if (empty($hops)) {
                return response()->json(['message' => 'Traceroute tidak mengembalikan hasil.'], 404);
            }

            return response()->json(['data' => $hops]);
        } catch (\Exception $e) {
            Log::error('Traceroute failed', [
                'tag' => $tag,
                'username' => $session->username,
                'error' => $e->getMessage()
            ]);
            return response()->json(['message' => 'Traceroute gagal: ' . $e->getMessage()], 500);
        }
    }
}
