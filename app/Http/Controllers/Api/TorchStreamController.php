<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TorchSession;
use App\Services\MikrotikApiService;
use App\Services\TorchGuardrailService;
use Illuminate\Support\Facades\Log;

/**
 * TorchStreamController — Menangani SSE (Server-Sent Events) untuk hasil Torch.
 *
 * @see SRD.md Section 4
 */
class TorchStreamController extends Controller
{
    public function __construct(
        private readonly MikrotikApiService $mikrotikApi,
        private readonly TorchGuardrailService $guardrailService,
        private readonly \App\Services\EnrichmentService $enrichmentService
    ) {}

    /**
     * GET /api/torch/{tag}/stream — SSE endpoint.
     *
     * @role TIER_2+
     */
    public function stream(string $tag)
    {
        if (str_starts_with($tag, 'demo_torch_')) {
            return $this->demoStream($tag);
        }

        $session = TorchSession::where('tag', $tag)->firstOrFail();

        if ($session->status !== 'RUNNING') {
            return response()->json(['message' => 'Sesi tidak aktif.'], 400);
        }

        // Matikan batas waktu default PHP untuk streaming
        set_time_limit(0);
        
        // Nonaktifkan output buffering
        while (ob_get_level()) {
            ob_end_clean();
        }

        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Penting untuk proxy Nginx/Caddy
        ];

        return response()->stream(function () use ($session, $tag) {
            $router = $session->router;
            $timeoutSeconds = config('netsight.torch.session_timeout', 120);
            $start = time();

            try {
                // Ambil IP user PPPoE aktif (Diagnostic Assistant)
                $userIp = null;
                $activeSessions = $this->mikrotikApi->getActivePppoeSession($router, $session->username);
                if (!empty($activeSessions) && isset($activeSessions[0]['address'])) {
                    $userIp = $activeSessions[0]['address'];
                }

                // Hubungkan ke Mikrotik satu kali
                $client = $this->mikrotikApi->connect($router);
                
                $loopCounter = 0;
                $samples = [];
                $appTotals = [];
                $peakTx = 0;
                $peakRx = 0;
                $totalTx = 0;
                $totalRx = 0;
                $sampleCount = 0;

                while (true) {
                    // Cek jika client disconnect
                    if (connection_aborted()) {
                        Log::info("Client disconnected from Torch SSE", ['tag' => $tag]);
                        break;
                    }

                    // Cek limit waktu sesi (mis. 120 detik)
                    if (time() - $start > $timeoutSeconds) {
                        Log::info("Torch session timeout reached", ['tag' => $tag]);
                        echo "event: timeout\n";
                        echo "data: Sesi telah mencapai batas waktu maksimum ({$timeoutSeconds}s).\n\n";
                        flush();
                        break;
                    }

                    // Cek di database jika status bukan RUNNING (mis. di-cancel oleh watchdog)
                    // HANYA CEK SETIAP 3 ITERASI KARENA read() MUNGKIN SANGAT CEPAT (TERGANTUNG TRAFIK)
                    if ($loopCounter % 3 === 0) {
                        $currentStatus = TorchSession::where('tag', $tag)->value('status');
                        if ($currentStatus !== 'RUNNING') {
                            Log::info("Torch session cancelled externally", ['tag' => $tag]);
                            echo "event: closed\n";
                            echo "data: Sesi ditutup secara eksternal.\n\n";
                            flush();
                            break;
                        }
                    }

                    try {
                        // Kumpulkan traffic batch selama 3 detik
                        $result = $this->mikrotikApi->runTorchBatch($client, $session->dynamic_interface, 3);
                    } catch (\Throwable $e) {
                        if (str_contains($e->getMessage(), 'Socket timeout reached') || str_contains($e->getMessage(), 'timed out')) {
                            // Ini wajar terjadi jika tidak ada trafik sama sekali di PPPoE (socket timeout).
                            // Kita cukup kirim keep-alive dan lanjutkan loop membaca.
                            echo ": keep-alive\n\n";
                            flush();
                            $loopCounter++;
                            
                            // Catat sampel 0 jika terjadi timeout (tidak ada trafik)
                            $samples[] = [
                                'time' => time(),
                                'tx' => 0,
                                'rx' => 0
                            ];
                            $sampleCount++;
                            continue;
                        }
                        
                        // Error lain yang fatal
                        throw $e;
                    }
                    
                    $batchTx = 0;
                    $batchRx = 0;

                    if (!empty($result) && is_array($result)) {
                        // Data Enrichment (Phase 4)
                        $enrichedResult = [];
                        foreach ($result as $packet) {
                            if (!isset($packet['dst-port']) || !isset($packet['dst-address'])) {
                                continue;
                            }
                            
                            $dstPortStr = $packet['dst-port'];
                            $dstPortNum = (int) $dstPortStr; // Extacts number from "443 (https)"
                            
                            $portInfo = $this->enrichmentService->classifyPort($dstPortNum);
                            
                            // Ambil dst-address tanpa port (IPv4) jika ada format IP:Port, meski biasanya Mikrotik pisah
                            $dstIp = explode(':', $packet['dst-address'] ?? '')[0];
                            $geoInfo = $this->enrichmentService->geoIpLookup($dstIp);
                            $asnInfo = $this->enrichmentService->asnLookup($dstIp);
                            
                            $protocol = $packet['ip-protocol'] ?? '';
                            $appInfo = $this->enrichmentService->identifyApp(
                                $asnInfo['asn_org'],
                                $asnInfo['asn_number'],
                                $dstPortNum,
                                $protocol
                            );

                            $txVal = (int)($packet['tx'] ?? 0);
                            $rxVal = (int)($packet['rx'] ?? 0);
                            $batchTx += $txVal;
                            $batchRx += $rxVal;
                            
                            $appTotals[$appInfo['name']] = ($appTotals[$appInfo['name']] ?? 0) + $txVal + $rxVal;

                            $enrichedResult[] = array_merge($packet, [
                                'port' => (string) $dstPortNum,
                                'protocol' => $protocol ?: '-',
                                '_enriched' => [
                                    'port_category' => $portInfo['category'],
                                    'port_service' => $portInfo['service'],
                                    'geo_country' => $geoInfo['country'],
                                    'geo_city' => $geoInfo['city'],
                                    'asn_org' => $asnInfo['asn_org'],
                                    'app_name' => $appInfo['name'],
                                    'app_icon' => $appInfo['icon'],
                                    'app_category' => $appInfo['category'],
                                ]
                            ]);
                        }

                        if (!empty($enrichedResult)) {
                            echo "data: " . json_encode(['type' => 'traffic', 'data' => $enrichedResult]) . "\n\n";
                        }
                    } else {
                        // Ping keep-alive jika kosong (jarang terjadi karena torch stream kontinu)
                        echo ": keep-alive\n\n";
                    }

                    // Catat sampel trafik untuk visualisasi chart history
                    $peakTx = max($peakTx, $batchTx);
                    $peakRx = max($peakRx, $batchRx);
                    $totalTx += $batchTx;
                    $totalRx += $batchRx;
                    
                    $samples[] = [
                        'time' => time(),
                        'tx' => $batchTx,
                        'rx' => $batchRx
                    ];
                    $sampleCount++;

                    // Lakukan Ping (count=3) setiap 2 iterasi (~6 detik) menggunakan koneksi yang sama
                    if ($userIp && $loopCounter % 2 === 0) {
                        try {
                            $pingRes = $this->mikrotikApi->pingUser($router, $userIp, 3, $client);
                            echo "data: " . json_encode(['type' => 'ping', 'data' => $pingRes]) . "\n\n";
                        } catch (\Exception $e) {
                            Log::error('Ping error in loop', ['e' => $e->getMessage()]);
                        }
                    }

                    // Lakukan fetch logs setiap 3 iterasi (~9 detik) menggunakan koneksi yang sama
                    if ($loopCounter % 3 === 0) {
                        try {
                            $logsRes = $this->mikrotikApi->getSystemLogs($router, 30, $client);
                            echo "data: " . json_encode(['type' => 'logs', 'data' => $logsRes]) . "\n\n";
                        } catch (\Exception $e) {
                            Log::error('Logs error in loop', ['e' => $e->getMessage()]);
                        }
                    }
                    
                    flush();
                    $loopCounter++;
                }

            } catch (\Throwable $e) {
                Log::error("Torch stream error", ['tag' => $tag, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                echo "event: error\n";
                echo "data: " . json_encode(['message' => 'Gagal membaca data dari router: ' . $e->getMessage()]) . "\n\n";
                flush();
            } finally {
                // Bersihkan proses di router dan update database
                try {
                    $this->mikrotikApi->cancelTorch($router, $tag);
                } catch (\Exception $e) {
                    // Abaikan jika sudah error
                }
                
                // Hitung rata-rata
                $avgTx = $sampleCount > 0 ? (int)($totalTx / $sampleCount) : 0;
                $avgRx = $sampleCount > 0 ? (int)($totalRx / $sampleCount) : 0;
                
                // Distribusi aplikasi
                $appDistribution = [];
                $totalAppTraffic = array_sum($appTotals);
                if ($totalAppTraffic > 0) {
                    arsort($appTotals);
                    foreach ($appTotals as $appName => $bytes) {
                        $appDistribution[] = [
                            'name' => $appName,
                            'bytes' => $bytes,
                            'percentage' => round(($bytes / $totalAppTraffic) * 100, 2)
                        ];
                    }
                }
                
                // Kesimpulan Diagnosa Otomatis
                $avgTxMbps = $avgTx / 1000000;
                $avgRxMbps = $avgRx / 1000000;
                $totalMbps = $avgTxMbps + $avgRxMbps;
                
                $conclusion = 'Koneksi normal. Latensi dan bandwidth terpantau stabil.';
                if ($totalMbps > 20) {
                    $conclusion = 'Bandwidth jenuh (Trafik berat). Kecepatan mungkin menurun akibat pemakaian penuh.';
                }

                $statusToUpdate = 'COMPLETED';
                $sessionDb = TorchSession::where('tag', $tag)->first();
                if ($sessionDb && in_array($sessionDb->status, ['CANCELLED', 'FORCE_TERMINATED'])) {
                    $statusToUpdate = $sessionDb->status;
                }

                TorchSession::where('tag', $tag)->update([
                    'status' => $statusToUpdate,
                    'ended_at' => now(),
                    'traffic_samples' => json_encode($samples),
                    'app_distribution' => json_encode($appDistribution),
                    'diagnostic_conclusion' => $conclusion,
                    'peak_tx_bps' => $peakTx,
                    'peak_rx_bps' => $peakRx,
                    'avg_tx_bps' => $avgTx,
                    'avg_rx_bps' => $avgRx,
                ]);

                $this->guardrailService->releaseHeartbeat($tag);
            }
        }, 200, $headers);
    }

    private function demoStream(string $tag)
    {
        set_time_limit(0);
        while (ob_get_level()) ob_end_clean();

        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];

        return response()->stream(function () {
            $loopCounter = 0;
            while (true) {
                if (connection_aborted()) break;

                // Pool of varied dummy applications
                $appPool = [
                    [
                        'ip' => '142.250.196.14:443', 'port' => '443', 'proto' => 'tcp',
                        'port_cat' => 'Web', 'svc' => 'HTTPS', 'country' => 'US', 'city' => 'Mountain View',
                        'org' => 'Google LLC', 'app' => 'YouTube', 'icon' => '📺', 'cat' => 'Streaming'
                    ],
                    [
                        'ip' => '162.159.130.23:443', 'port' => '443', 'proto' => 'tcp',
                        'port_cat' => 'Web', 'svc' => 'HTTPS', 'country' => 'US', 'city' => 'San Francisco',
                        'org' => 'Cloudflare, Inc.', 'app' => 'TikTok', 'icon' => '📱', 'cat' => 'Social Media'
                    ],
                    [
                        'ip' => '157.240.22.35:443', 'port' => '443', 'proto' => 'tcp',
                        'port_cat' => 'Web', 'svc' => 'HTTPS', 'country' => 'US', 'city' => 'Menlo Park',
                        'org' => 'Meta Platforms', 'app' => 'Instagram', 'icon' => '📷', 'cat' => 'Social Media'
                    ],
                    [
                        'ip' => '45.57.90.1:443', 'port' => '443', 'proto' => 'tcp',
                        'port_cat' => 'Web', 'svc' => 'HTTPS', 'country' => 'US', 'city' => 'Los Gatos',
                        'org' => 'Netflix', 'app' => 'Netflix', 'icon' => '🎬', 'cat' => 'Streaming'
                    ],
                    [
                        'ip' => '162.254.196.50:27015', 'port' => '27015', 'proto' => 'udp',
                        'port_cat' => 'Gaming', 'svc' => 'Steam', 'country' => 'US', 'city' => 'Bellevue',
                        'org' => 'Valve Corporation', 'app' => 'Steam', 'icon' => '🎮', 'cat' => 'Gaming'
                    ],
                    [
                        'ip' => '170.114.14.3:8801', 'port' => '8801', 'proto' => 'udp',
                        'port_cat' => 'Video', 'svc' => 'Zoom', 'country' => 'US', 'city' => 'San Jose',
                        'org' => 'Zoom Video Comm.', 'app' => 'Zoom', 'icon' => '🎥', 'cat' => 'Communication'
                    ]
                ];

                // Randomly pick 2 to 4 apps active for this 3-second window
                shuffle($appPool);
                $activeApps = array_slice($appPool, 0, rand(2, 4));

                $demoData = [];
                foreach ($activeApps as $app) {
                    $demoData[] = [
                        'tx' => rand(500000, 3000000), // 0.5 - 3 Mbps per app
                        'rx' => rand(1000000, 8000000), // 1 - 8 Mbps per app
                        'ip-protocol' => $app['proto'],
                        'dst-port' => "{$app['port']} ({$app['svc']})",
                        'dst-address' => $app['ip'],
                        'port' => $app['port'],
                        'protocol' => $app['proto'],
                        '_enriched' => [
                            'port_category' => $app['port_cat'],
                            'port_service' => $app['svc'],
                            'geo_country' => $app['country'],
                            'geo_city' => $app['city'],
                            'asn_org' => $app['org'],
                            'app_name' => $app['app'],
                            'app_icon' => $app['icon'],
                            'app_category' => $app['cat'],
                        ]
                    ];
                }

                echo "data: " . json_encode(['type' => 'traffic', 'data' => $demoData]) . "\n\n";

                if ($loopCounter % 2 === 0) {
                    $pingRes = [
                        ['status' => 'OK', 'size' => 56, 'ttl' => 62, 'time' => rand(15, 30) . 'ms', 'sent' => 3, 'received' => 3, 'packet-loss' => '0%']
                    ];
                    echo "data: " . json_encode(['type' => 'ping', 'data' => $pingRes]) . "\n\n";
                }

                flush();
                sleep(3);
                $loopCounter++;
            }
        }, 200, $headers);
    }
}
