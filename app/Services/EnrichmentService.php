<?php

namespace App\Services;

/**
 * EnrichmentService — Port classification, GeoIP lookup.
 *
 * Stub untuk Fase 4 (Data Enrichment & Visualisasi).
 *
 * @see PRD.md Section 5 — Feature 4: Deep-Dive
 * @see SRD.md Section 4 step 11
 */
class EnrichmentService
{
    /**
     * Klasifikasi port berdasarkan nomor port.
     *
     * @return array{category: string, service: string, description: string}
     */
    public function classifyPort(int $port): array
    {
        // Daftar port umum yang relevan untuk ISP/NOC
        $portMap = [
            80 => ['category' => 'Web', 'service' => 'HTTP', 'description' => 'Web browsing'],
            443 => ['category' => 'Web', 'service' => 'HTTPS', 'description' => 'Secure web browsing'],
            8080 => ['category' => 'Web', 'service' => 'HTTP-Alt', 'description' => 'Alternative web'],
            53 => ['category' => 'DNS', 'service' => 'DNS', 'description' => 'Domain name resolution'],
            22 => ['category' => 'Remote', 'service' => 'SSH', 'description' => 'Secure shell'],
            21 => ['category' => 'File Transfer', 'service' => 'FTP', 'description' => 'File transfer'],
            25 => ['category' => 'Email', 'service' => 'SMTP', 'description' => 'Email sending'],
            110 => ['category' => 'Email', 'service' => 'POP3', 'description' => 'Email retrieval'],
            143 => ['category' => 'Email', 'service' => 'IMAP', 'description' => 'Email access'],
            993 => ['category' => 'Email', 'service' => 'IMAPS', 'description' => 'Secure email access'],
            3389 => ['category' => 'Remote', 'service' => 'RDP', 'description' => 'Remote desktop'],
            1194 => ['category' => 'VPN', 'service' => 'OpenVPN', 'description' => 'VPN tunnel'],
            51820 => ['category' => 'VPN', 'service' => 'WireGuard', 'description' => 'VPN tunnel'],
            1935 => ['category' => 'Streaming', 'service' => 'RTMP', 'description' => 'Video streaming'],
            5060 => ['category' => 'VoIP', 'service' => 'SIP', 'description' => 'Voice over IP'],
            3478 => ['category' => 'VoIP', 'service' => 'STUN', 'description' => 'NAT traversal'],
        ];

        if (isset($portMap[$port])) {
            return $portMap[$port];
        }

        // Gaming ports range
        if (($port >= 27000 && $port <= 27050) || ($port >= 3074 && $port <= 3080)) {
            return ['category' => 'Gaming', 'service' => 'Game', 'description' => 'Online gaming'];
        }

        // Torrent range
        if ($port >= 6881 && $port <= 6889) {
            return ['category' => 'P2P', 'service' => 'BitTorrent', 'description' => 'Peer-to-peer'];
        }

        return ['category' => 'Other', 'service' => 'Unknown', 'description' => "Port {$port}"];
    }

    /**
     * GeoIP lookup menggunakan database lokal MaxMind.
     *
     * @see PRD.md Section 4 — MaxMind GeoIP2 (lokal .mmdb)
     * @see SECURITY.md Section 7 — .mmdb diperbarui bulanan, tetap lokal
     */
    public function geoIpLookup(string $ipAddress): array
    {
        // 1. Cek apakah ini Private IP (RFC 1918)
        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return [
                'ip' => $ipAddress,
                'country' => 'Local Network',
                'city' => '-',
                'isp' => 'Internal',
            ];
        }

        // 2. Baca file MMDB Lokal
        $dbPath = storage_path('app/geoip/GeoLite2-City.mmdb');
        
        if (!file_exists($dbPath)) {
            return [
                'ip' => $ipAddress,
                'country' => 'Unknown',
                'city' => '-',
                'isp' => '-',
                'note' => 'MMDB file missing',
            ];
        }

        try {
            $reader = new \GeoIp2\Database\Reader($dbPath);
            $record = $reader->city($ipAddress);

            return [
                'ip' => $ipAddress,
                'country' => $record->country->name ?? 'Unknown',
                'city' => $record->city->name ?? '-',
                'isp' => '-', // GeoLite2-City doesn't have ISP. ASN DB needed for ISP.
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('GeoIP lookup failed', [
                'ip' => $ipAddress,
                'error' => $e->getMessage()
            ]);

            return [
                'ip' => $ipAddress,
                'country' => 'Unknown',
                'city' => '-',
                'isp' => '-',
            ];
        }
    }

    /**
     * ASN lookup menggunakan database lokal MaxMind GeoLite2-ASN.
     */
    public function asnLookup(string $ipAddress): array
    {
        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return [
                'asn_number' => 0,
                'asn_org' => 'Private Network',
            ];
        }

        $dbPath = storage_path('app/geoip/GeoLite2-ASN.mmdb');
        
        if (!file_exists($dbPath)) {
            return [
                'asn_number' => 0,
                'asn_org' => 'Unknown (No DB)',
            ];
        }

        try {
            $reader = new \GeoIp2\Database\Reader($dbPath);
            $record = $reader->asn($ipAddress);

            return [
                'asn_number' => $record->autonomousSystemNumber ?? 0,
                'asn_org' => $record->autonomousSystemOrganization ?? 'Unknown',
            ];
        } catch (\Exception $e) {
            return [
                'asn_number' => 0,
                'asn_org' => 'Unknown',
            ];
        }
    }

    /**
     * Identifikasi aplikasi dari ASN, port, dan protocol
     */
    public function identifyApp(string $asnOrg, int $asnNumber, int $port, string $protocol): array
    {
        $name = strtolower($asnOrg);
        
        // Custom Heuristics based on Ports/Protocols
        if (str_contains($protocol, 'udp')) {
            // Mobile Legends often uses 5001 or 9000-9010 UDP
            if ($port === 5001 || ($port >= 9000 && $port <= 9010) || ($port >= 30000 && $port <= 30150)) {
                return ['name' => 'Mobile Legends', 'icon' => '⚔️', 'category' => 'Gaming'];
            }
            // WhatsApp / STUN / WebRTC often uses 3478 UDP
            if ($port === 3478 || $port === 3479) {
                return ['name' => 'WhatsApp / VoIP', 'icon' => '📞', 'category' => 'VoIP'];
            }
        }

        // ASN Heuristics
        if (str_contains($name, 'bytedance') || str_contains($name, 'tiktok') || in_array($asnNumber, [138699, 396986, 139582])) {
            return ['name' => 'TikTok', 'icon' => '🎵', 'category' => 'Social Media'];
        }
        
        if (str_contains($name, 'google') || str_contains($name, 'youtube') || in_array($asnNumber, [15169, 36040, 43515])) {
            return ['name' => 'YouTube / Google', 'icon' => '🎬', 'category' => 'Streaming'];
        }
        
        if (str_contains($name, 'facebook') || str_contains($name, 'instagram') || str_contains($name, 'meta') || in_array($asnNumber, [32934])) {
            return ['name' => 'Meta Services', 'icon' => '💬', 'category' => 'Social Media'];
        }
        
        if (str_contains($name, 'netflix') || in_array($asnNumber, [2906])) {
            return ['name' => 'Netflix', 'icon' => '🍿', 'category' => 'Streaming'];
        }

        if (str_contains($name, 'akamai') || in_array($asnNumber, [20940, 16625])) {
            return ['name' => 'CDN (Akamai)', 'icon' => '⚡', 'category' => 'CDN'];
        }

        if (str_contains($name, 'cloudflare') || in_array($asnNumber, [13335])) {
            return ['name' => 'CDN (Cloudflare)', 'icon' => '⚡', 'category' => 'CDN'];
        }

        if (str_contains($name, 'amazon') || str_contains($name, 'aws') || in_array($asnNumber, [16509, 14618])) {
            return ['name' => 'AWS / Cloud', 'icon' => '☁️', 'category' => 'Cloud'];
        }

        if (str_contains($name, 'microsoft') || str_contains($name, 'azure') || in_array($asnNumber, [8075])) {
            return ['name' => 'Microsoft / Xbox', 'icon' => '🎮', 'category' => 'Cloud / Gaming'];
        }

        if (str_contains($name, 'apple') || in_array($asnNumber, [714])) {
            return ['name' => 'Apple / iCloud', 'icon' => '🍎', 'category' => 'Cloud'];
        }

        if (str_contains($name, 'valve') || in_array($asnNumber, [32590])) {
            return ['name' => 'Steam / Valve', 'icon' => '🎮', 'category' => 'Gaming'];
        }

        if (str_contains($name, 'telegram') || in_array($asnNumber, [62041, 62014])) {
            return ['name' => 'Telegram', 'icon' => '✈️', 'category' => 'Social Media'];
        }

        if (str_contains($name, 'spotify') || in_array($asnNumber, [8403])) {
            return ['name' => 'Spotify', 'icon' => '🎧', 'category' => 'Streaming'];
        }

        // Fallback to Port Classification
        $portClass = $this->classifyPort($port);
        $icon = match($portClass['category']) {
            'Web' => '🌐',
            'Gaming' => '🎮',
            'Streaming' => '📺',
            'P2P' => '🔄',
            'DNS' => '🔍',
            'VoIP' => '📞',
            default => '📦'
        };

        return [
            'name' => $portClass['category'] === 'Other' ? 'Other / Unknown' : $portClass['service'],
            'icon' => $icon,
            'category' => $portClass['category']
        ];
    }
}
