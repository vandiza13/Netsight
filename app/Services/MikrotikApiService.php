<?php

namespace App\Services;

use App\Models\Router;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Exceptions\ClientException;
use RouterOS\Exceptions\ConnectException;
use RouterOS\Query;

/**
 * MikrotikApiService — Wrapper tunggal untuk SEMUA panggilan API MikroTik.
 *
 * ATURAN PENTING (AGENT.md Section 2):
 * - SEMUA panggilan ke MikroTik API WAJIB lewat service ini.
 * - DILARANG instansiasi client routeros-api-php langsung di controller/job manapun.
 * - Input sanitization diterapkan di sini sebelum dikirim ke router.
 * - Retry dengan exponential backoff dari config.
 *
 * @see SECURITY.md Section 4.2 — Input Sanitization
 * @see SECURITY.md Section 4.1 — Koneksi terenkripsi (API-SSL port 8729)
 * @see SRD.md Section 5 — Retry Policy
 */
class MikrotikApiService
{
    /**
     * Buat koneksi ke router MikroTik.
     *
     * @throws ConnectException Jika koneksi gagal setelah retry
     */
    public function connect(Router $router): Client
    {
        $credential = $router->credential;

        if (empty($credential)) {
            throw new InvalidArgumentException(
                "Router [{$router->name}] credential is empty or cannot be decrypted."
            );
        }

        $port = $router->api_port ?? config('netsight.api.default_port');
        $useSsl = ($port == 8729);

        $config = new Config([
            'host' => $router->host,
            'port' => $port,
            'user' => $router->api_user, // Menggunakan kolom api_user khusus
            'pass' => $credential,
            'ssl'  => $useSsl,
            'ssl_options' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
            'timeout' => config('netsight.api.connection_timeout_seconds'),
        ]);

        return $this->connectWithRetry($config, $router);
    }

    /**
     * Koneksi dengan retry dan exponential backoff.
     *
     * @see SRD.md Section 5 — Maksimal 3x retry, lalu berhenti
     * @throws ConnectException
     */
    private function connectWithRetry(Config $config, Router $router): Client
    {
        $maxRetries = config('netsight.api.max_retries');
        $backoffSeconds = config('netsight.api.backoff_seconds');
        $lastException = null;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                return new Client($config);
            } catch (ConnectException $e) {
                $lastException = $e;

                Log::warning("MikroTik API connection attempt {$attempt}/{$maxRetries} failed", [
                    'router' => $router->name,
                    'host' => $router->host,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < $maxRetries) {
                    $delay = $backoffSeconds[$attempt] ?? end($backoffSeconds);
                    sleep($delay);
                }
            }
        }

        Log::error("MikroTik API connection failed after {$maxRetries} retries", [
            'router' => $router->name,
            'host' => $router->host,
        ]);

        throw $lastException;
    }

    /**
     * Eksekusi query ke router MikroTik.
     *
     * @param Router $router Router target
     * @param string $command Command MikroTik (contoh: '/ppp/active/print')
     * @param array $where Filter conditions (key => value)
     * @return array Response dari router
     * @throws ConnectException|ClientException
     */
    public function execute(Router $router, string $command, array $where = []): array
    {
        $client = $this->connect($router);

        $query = new Query($command);

        foreach ($where as $key => $value) {
            // Gunakan parameterized query — DILARANG string concatenation
            // @see SECURITY.md Section 4.2
            $query->where($key, $value);
        }

        return $client->query($query)->read();
    }

    /**
     * Cek CPU load router via /system resource print.
     *
     * @see SRD.md Section 4 step 2
     * @see SECURITY.md Section 4.3
     * @return array{cpu_load: int, free_memory: int, total_memory: int}
     */
    public function getSystemResource(Router $router): array
    {
        $result = $this->execute($router, '/system/resource/print');

        if (empty($result)) {
            throw new ClientException("Empty response from /system/resource/print on [{$router->name}]");
        }

        $resource = $result[0];

        return [
            'cpu_load' => (int) ($resource['cpu-load'] ?? 0),
            'free_memory' => (int) ($resource['free-memory'] ?? 0),
            'total_memory' => (int) ($resource['total-memory'] ?? 0),
            'uptime' => $resource['uptime'] ?? 'unknown',
            'version' => $resource['version'] ?? 'unknown',
        ];
    }

    /**
     * Ambil daftar PPPoE secret dari router.
     *
     * @see SRD.md Section 1 — Background sync
     */
    public function getPppoeSecrets(Router $router): array
    {
        return $this->execute($router, '/ppp/secret/print');
    }

    /**
     * Ambil daftar PPPoE profile dari router.
     * Digunakan untuk mengambil default rate limit tiap profil.
     */
    public function getPppoeProfiles(Router $router): array
    {
        return $this->execute($router, '/ppp/profile/print');
    }

    /**
     * Ambil sesi PPPoE aktif, opsional filter per username.
     *
     * @see SRD.md Section 4 step 4
     */
    public function getActivePppoeSession(Router $router, ?string $username = null): array
    {
        $where = [];

        if ($username !== null) {
            $this->validateUsername($username);
            $where['name'] = $username;
        }

        return $this->execute($router, '/ppp/active/print', $where);
    }

    /**
     * Helper untuk mendapatkan interface name dinamis dari user PPPoE.
     */
    public function getActivePppoeInterface(Router $router, string $username): ?string
    {
        $sessions = $this->getActivePppoeSession($router, $username);
        if (empty($sessions)) {
            return null;
        }
        
        return "<pppoe-{$username}>";
    }

    /**
     * Melakukan ping dari router ke alamat IP pengguna.
     * 
     * @return array Array respons dari perintah ping
     */
    public function pingUser(Router $router, string $ipAddress, int $count = 1, ?Client $client = null): array
    {
        // Validasi format IP sederhana untuk keamanan tambahan
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            Log::warning("Invalid IP address passed to pingUser", ['ip' => $ipAddress]);
            return [];
        }

        try {
            $apiClient = $client ?? $this->connect($router);
            $query = (new Query('/ping'))
                ->equal('address', $ipAddress)
                ->equal('count', (string) $count);

            return $apiClient->query($query)->read();
        } catch (\Exception $e) {
            Log::error("Failed to ping user", [
                'router' => $router->name,
                'ip' => $ipAddress,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Dapatkan IP address PPPoE aktif dari router berdasarkan username
     */
    public function getPppoeIpAddress(Router $router, string $username): ?string
    {
        try {
            $client = $this->connect($router);
            $query = (new Query('/ppp/active/print'))->where('name', $username);
            $active = $client->query($query)->read();
            if (!empty($active) && isset($active[0]['address'])) {
                return $active[0]['address'];
            }
        } catch (\Exception $e) {
            Log::error("Failed to get PPPoE IP for user", ['router' => $router->name, 'user' => $username, 'error' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * Melakukan traceroute ke IP tertentu dari router
     */
    public function tracerouteUser(Router $router, string $ipAddress): array
    {
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            Log::warning("Invalid IP address passed to tracerouteUser", ['ip' => $ipAddress]);
            return [];
        }

        try {
            $client = $this->connect($router);
            $query = (new Query('/tool/traceroute'))
                ->equal('address', $ipAddress)
                ->equal('count', '1')
                ->equal('use-dns', 'no'); // Disable DNS to speed it up

            // Traceroute with count=1 returns all hops up to the destination
            return $client->query($query)->read();
        } catch (\Exception $e) {
            Log::error("Failed to traceroute user", [
                'router' => $router->name,
                'ip' => $ipAddress,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Mengambil versi RouterOS dari router
     */
    public function getRouterVersion(Router $router): ?string
    {
        try {
            $client = $this->connect($router);
            $query = new Query('/system/resource/print');
            $response = $client->query($query)->read();
            
            if (!empty($response) && isset($response[0]['version'])) {
                // Return only the version number, e.g., "7.22.2" instead of "7.22.2 (stable)"
                $parts = explode(' ', $response[0]['version']);
                return $parts[0];
            }
            return null;
        } catch (\Exception $e) {
            Log::warning("Failed to get router version", [
                'router' => $router->name,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Mengambil system log dari router (fokus ke warning, error, pppoe, radius)
     */
    public function getSystemLogs(Router $router, int $limit = 50, ?Client $client = null): array
    {
        try {
            $apiClient = $client ?? $this->connect($router);
            // Query logs in reverse order to get latest first. Since we can't easily reverse via API, 
            // we will fetch logs and array_reverse in PHP.
            $query = new Query('/log/print');
            $logs = $apiClient->query($query)->read();
            
            // Reverse to get latest logs first
            $logs = array_reverse($logs);
            
            return array_slice($logs, 0, $limit);
        } catch (\Exception $e) {
            Log::error("Failed to fetch system logs", [
                'router' => $router->name,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Mengambil konfigurasi Queue (bandwidth limit) untuk user PPPoE
     */
    public function getUserQueueLimit(Router $router, string $username): ?array
    {
        try {
            $client = $this->connect($router);
            
            // Biasanya queue dinamis PPPoE memiliki nama sama dengan interface PPPoE-nya ("<pppoe-username>")
            $query = (new Query('/queue/simple/print'))->where('name', "<pppoe-$username>");
            $queues = $client->query($query)->read();
            
            if (empty($queues)) {
                // Jika tidak ketemu pakai kurung sudut, coba dengan nama username biasa
                $query = (new Query('/queue/simple/print'))->where('name', $username);
                $queues = $client->query($query)->read();
            }

            if (!empty($queues)) {
                $q = $queues[0];
                // max-limit format is usually "tx/rx" e.g. "10000000/20000000" (Upload/Download in bps)
                $maxLimit = $q['max-limit'] ?? '0/0';
                $parts = explode('/', $maxLimit);
                
                return [
                    'name' => $q['name'],
                    'tx_limit' => (int)($parts[0] ?? 0),
                    'rx_limit' => (int)($parts[1] ?? 0),
                    'burst_limit' => $q['burst-limit'] ?? null,
                    'dynamic' => isset($q['dynamic']) ? ($q['dynamic'] === 'true' || $q['dynamic'] === 'yes') : false,
                ];
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error("Failed to fetch user queue limit", [
                'router' => $router->name,
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Jalankan sesi Torch pada interface tertentu dan baca hasilnya.
     *
     * @see SRD.md Section 4 step 9
     */
    public function runTorchBatch(
        Client $client,
        string $interface,
        int $duration = 3
    ): array {
        $query = (new Query('/tool/torch'))
            ->equal('interface', $interface)
            ->equal('src-address', '0.0.0.0/0')
            ->equal('dst-address', '0.0.0.0/0')
            ->equal('port', 'any')
            ->equal('duration', (string)$duration);

        return $client->query($query)->read();
    }

    /**
     * Cancel sesi Torch berdasarkan tag.
     *
     * @see SRD.md Section 4 step 12-13
     */
    public function cancelTorch(Router $router, string $tag): void
    {
        try {
            $client = $this->connect($router);

            $query = (new Query('/cancel'))
                ->tag($tag);

            $client->query($query);

            Log::info("Torch session cancelled", [
                'router' => $router->name,
                'tag' => $tag,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to cancel Torch session", [
                'router' => $router->name,
                'tag' => $tag,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Validasi username PPPoE — whitelist karakter saja.
     *
     * Hanya alfanumerik, titik, underscore, dan strip yang diizinkan.
     * DILARANG membangun string command lewat concatenation manual.
     *
     * @see SECURITY.md Section 4.2
     * @throws InvalidArgumentException
     */
    public function validateUsername(string $username): void
    {
        $pattern = config('netsight.sanitization.username_pattern');

        if (! preg_match($pattern, $username)) {
            throw new InvalidArgumentException(
                "Username contains invalid characters. Only alphanumeric, dots, underscores, and hyphens are allowed."
            );
        }

        if (strlen($username) > 100) {
            throw new InvalidArgumentException(
                "Username exceeds maximum length of 100 characters."
            );
        }
    }
}
