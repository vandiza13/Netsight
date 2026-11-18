<?php

/**
 * NETSIGHT v2.1 — Configuration
 *
 * Semua angka guardrail dikumpulkan di sini.
 * JANGAN hardcode nilai-nilai ini di service/controller.
 * Bandwidth agnostic: bandwidth_capacity_mbps null = unlimited, guard CPU-based bukan Gbps (bukan hard 1.5G).
 * @see AGENT.md Section 2
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Torch Engine Configuration
    |--------------------------------------------------------------------------
    | Guardrail untuk sesi Torch yang menyentuh router produksi.
    | @see SRD.md Section 4, SECURITY.md Section 5
    */
    'torch' => [
        // Tolak Torch jika CPU router > threshold ini (%)
        'cpu_threshold' => (int) env('NETSIGHT_TORCH_CPU_THRESHOLD', 80),

        // Warning jika CPU di antara warning dan threshold
        'cpu_warning' => (int) env('NETSIGHT_TORCH_CPU_WARNING', 60),

        // Maksimal sesi Torch bersamaan per router
        'max_concurrent_per_router' => (int) env('NETSIGHT_TORCH_MAX_CONCURRENT', 2),

        // Maksimal sesi Torch bersamaan secara global (lintas sandbox/user)
        'max_global_concurrent' => (int) env('NETSIGHT_TORCH_MAX_GLOBAL_CONCURRENT', 5),

        // Hard limit durasi sesi Torch (detik) — terpisah dari connection timeout
        'session_timeout_seconds' => (int) env('NETSIGHT_TORCH_SESSION_TIMEOUT', 120),

        // TTL heartbeat lock di Redis (detik)
        'heartbeat_ttl_seconds' => (int) env('NETSIGHT_TORCH_HEARTBEAT_TTL', 10),

        // Interval refresh heartbeat lock (detik)
        'heartbeat_refresh_seconds' => (int) env('NETSIGHT_TORCH_HEARTBEAT_REFRESH', 5),

        // Jendela waktu race condition antara deteksi session dan eksekusi (detik)
        'race_condition_window_seconds' => (int) env('NETSIGHT_TORCH_RACE_WINDOW', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Background Sync Configuration
    |--------------------------------------------------------------------------
    | Konfigurasi untuk sinkronisasi data PPPoE dari router.
    | @see SRD.md Section 1, SECURITY.md Section 5
    */
    'sync' => [
        // Maksimal router yang di-sync secara paralel
        'max_parallel_routers' => (int) env('NETSIGHT_SYNC_MAX_PARALLEL', 5),

        // Jumlah kegagalan berturut-turut sebelum circuit breaker aktif
        'circuit_breaker_threshold' => (int) env('NETSIGHT_SYNC_CB_THRESHOLD', 3),

        // Durasi cooldown setelah circuit breaker aktif (menit)
        'cooldown_minutes' => (int) env('NETSIGHT_SYNC_COOLDOWN_MINUTES', 15),

        // Rate limit force-sync per router (menit)
        'force_sync_rate_limit_minutes' => (int) env('NETSIGHT_SYNC_RATE_LIMIT_MINUTES', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | MikroTik API Configuration
    |--------------------------------------------------------------------------
    | Timeout dan retry untuk koneksi ke router MikroTik.
    | @see SRD.md Section 5
    */
    'api' => [
        // Connection timeout ke API MikroTik (detik)
        'connection_timeout_seconds' => (int) env('NETSIGHT_API_TIMEOUT', 5),

        // Maksimal retry untuk kegagalan sementara
        'max_retries' => (int) env('NETSIGHT_API_MAX_RETRIES', 3),

        // Exponential backoff intervals (detik)
        'backoff_seconds' => [1, 3],

        // Port default API-SSL MikroTik
        'default_port' => (int) env('NETSIGHT_API_DEFAULT_PORT', 8729),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Configuration
    |--------------------------------------------------------------------------
    | @see SECURITY.md Section 3
    */
    'auth' => [
        // Session timeout (jam)
        'session_timeout_hours' => (int) env('NETSIGHT_AUTH_SESSION_TIMEOUT', 8),

        // Minimum panjang password
        'password_min_length' => (int) env('NETSIGHT_AUTH_PASSWORD_MIN_LENGTH', 12),

        // TOTP window (jumlah period yang diterima)
        'totp_window' => (int) env('NETSIGHT_AUTH_TOTP_WINDOW', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Watchdog Configuration
    |--------------------------------------------------------------------------
    | Cron independen untuk deteksi orphaned session.
    | @see SRD.md Section 4 step 14
    */
    'watchdog' => [
        // Interval pengecekan orphaned session (detik)
        'interval_seconds' => (int) env('NETSIGHT_WATCHDOG_INTERVAL', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Input Sanitization
    |--------------------------------------------------------------------------
    | @see SECURITY.md Section 4.2
    */
    'sanitization' => [
        // Regex whitelist untuk username PPPoE (alfanumerik, titik, underscore, strip, dan @ untuk email)
        'username_pattern' => '/^[a-zA-Z0-9._\-@]+$/',
    ],

    /*
    |--------------------------------------------------------------------------
    | TR-069 ACS Security, Rate Limiting & Queue Guardrails
    |--------------------------------------------------------------------------
    | Pembatasan laju eksekusi perintah GenieACS agar CPE, NBI, dan antrean
    | worker tetap aman pada skala besar (Fase 5).
    */
    'acs' => [
        // Cooldown per perangkat per tipe aksi (detik)
        'device_cooldowns' => [
            'refresh' => (int) env('NETSIGHT_ACS_CD_REFRESH', 10),
            'refresh_hosts' => (int) env('NETSIGHT_ACS_CD_REFRESH_HOSTS', 10),
            'update_wifi' => (int) env('NETSIGHT_ACS_CD_WIFI', 10),
            'update_radio' => (int) env('NETSIGHT_ACS_CD_RADIO', 10),
            'update_pppoe' => (int) env('NETSIGHT_ACS_CD_PPPOE', 15),
            'reboot' => (int) env('NETSIGHT_ACS_CD_REBOOT', 30),
            'factory_reset' => (int) env('NETSIGHT_ACS_CD_FACTORY_RESET', 60),
            'provision' => (int) env('NETSIGHT_ACS_CD_PROVISION', 15),
            'diagnostics' => (int) env('NETSIGHT_ACS_CD_DIAGNOSTICS', 15),
        ],

        // Cooldown sinkronisasi penuh manual (detik)
        'sync_cooldown_seconds' => (int) env('NETSIGHT_ACS_SYNC_COOLDOWN', 30),

        // Batas maksimal perangkat per operasi massal & proteksi antrean
        'bulk_max_devices' => (int) env('NETSIGHT_ACS_BULK_MAX_DEVICES', 500),
        'bulk_cooldown_seconds' => (int) env('NETSIGHT_ACS_BULK_COOLDOWN', 30),
        'bulk_chunk_size' => (int) env('NETSIGHT_ACS_BULK_CHUNK_SIZE', 25),
        'bulk_delay_ms' => (int) env('NETSIGHT_ACS_BULK_DELAY_MS', 100),
        'bulk_consecutive_failure_limit' => (int) env('NETSIGHT_ACS_BULK_CB_LIMIT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | License Configuration
    |--------------------------------------------------------------------------
    | Pengaturan untuk validasi lisensi ke server sentral.
    */
    'license' => [
        'key' => env('LICENSE_KEY', ''),
        'central_url' => env('NETSIGHT_CENTRAL_URL', 'https://central.vandiza.com'),
        'public_key' => env('NETSIGHT_PUBLIC_KEY', ''),
    ],

];
