# SRD: NETSIGHT v2.1
## Software/System Requirements Document
**Status:** Ready for Execution | **Merujuk ke:** `PRD.md`, `AGENT.md`, `SECURITY.md`

---

## 1. ARSITEKTUR SISTEM

```
[ Vue 3 + TS Dashboard ] ←── WebSocket (Reverb, Redis scaling driver) ── notifikasi status/lock
       │  ▲
       │  └── SSE stream ──────────────────────────────────────────── data Torch live
       ▼
[ Laravel 13 Backend ]
       ├── Laravel Horizon (Redis-backed queue) → Background Sync per-router (staggered)
       ├── Redis 8.6 → Heartbeat lock, circuit breaker, rate limit, cache
       ├── PostgreSQL 18 → User data, audit trail, log, enrichment data (JSONB)
       ├── evilfreelancer/routeros-api-php → Koneksi API-SSL (8729) ke MikroTik
       └── MaxMind GeoIP2 (lokal .mmdb) → Enrichment IP tujuan
       ▼
[ Core MikroTik Router — VPN Site-to-Site atau API-SSL langsung ]
```

## 2. SKEMA DATABASE (POSTGRESQL 18)

```sql
CREATE TABLE routers (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    host VARCHAR(100) NOT NULL,
    api_port INTEGER DEFAULT 8729,
    credential_encrypted TEXT NOT NULL, -- AES-256-CBC via Crypt::encryptString()
    routeros_version VARCHAR(10),
    sync_offset_minutes INTEGER NOT NULL, -- hash(router_id % 60)
    status VARCHAR(20) DEFAULT 'HEALTHY', -- HEALTHY | DEGRADED | UNREACHABLE
    last_synced_at TIMESTAMP,
    consecutive_sync_failures INTEGER DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE pppoe_users_cache (
    id BIGSERIAL PRIMARY KEY,
    router_id BIGINT REFERENCES routers(id),
    username VARCHAR(100) NOT NULL,
    profile VARCHAR(100),
    package_limit_mbps INTEGER,
    is_active_last_check BOOLEAN,
    synced_at TIMESTAMP,
    UNIQUE(router_id, username)
);

CREATE TABLE torch_sessions (
    id BIGSERIAL PRIMARY KEY,
    router_id BIGINT REFERENCES routers(id),
    username VARCHAR(100) NOT NULL,
    session_id_snapshot VARCHAR(100), -- validator race condition
    dynamic_interface VARCHAR(150),
    initiated_by BIGINT REFERENCES staff_noc(id),
    tag VARCHAR(50) NOT NULL,
    status VARCHAR(20) DEFAULT 'RUNNING', -- RUNNING | COMPLETED | CANCELLED | FORCE_TERMINATED
    auto_cleanup BOOLEAN DEFAULT FALSE,
    started_at TIMESTAMP,
    ended_at TIMESTAMP
);

CREATE TABLE audit_logs (
    id BIGSERIAL PRIMARY KEY,
    staff_noc_id BIGINT REFERENCES staff_noc(id),
    action VARCHAR(100),
    target_username VARCHAR(100),
    router_id BIGINT REFERENCES routers(id),
    metadata JSONB,
    created_at TIMESTAMP
);

CREATE TABLE staff_noc (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    password_hash VARCHAR(255),
    totp_secret_encrypted TEXT,
    role VARCHAR(20) NOT NULL, -- TIER_1 | TIER_2 | ADMIN
    created_at TIMESTAMP
);
```

## 3. SPESIFIKASI API ENDPOINT

| Method | Endpoint | Role Minimum | Deskripsi |
|---|---|---|---|
| POST | `/api/auth/login` | Public | Login + password, return challenge TOTP |
| POST | `/api/auth/totp-verify` | Public | Verifikasi TOTP, return session token |
| GET | `/api/routers` | TIER_1 | Daftar router + status (HEALTHY/DEGRADED/UNREACHABLE) |
| GET | `/api/routers/{id}/users?search=` | TIER_1 | Cache user PPPoE, query dari database lokal |
| POST | `/api/routers/{id}/force-sync` | TIER_2 | Rate-limited 1x/5menit, trigger sync manual |
| POST | `/api/torch/inspect` | TIER_2 | Body: `{router_id, username}` → mulai sesi Torch (SSE stream) |
| POST | `/api/torch/{session_id}/cancel` | TIER_2 | Batalkan sesi torch manual |
| GET | `/api/torch/{session_id}/stream` | TIER_2 | SSE endpoint, enrichment data real-time |
| GET | `/api/audit-logs` | ADMIN | Daftar log audit dengan filter |
| GET | `/api/routers/{id}/health-check` | TIER_2 | Trigger `/system resource print` (pre-flight CPU check) |

**Catatan wajib untuk semua endpoint di atas selain login/totp:**
- Middleware RBAC harus memfilter response **sebelum** dikirim, bukan mengandalkan frontend untuk redact data. TIER_1 yang memanggil `/api/torch/*` harus mendapat `403 Forbidden`, bukan data ter-redact.
- Middleware IP Whitelist diterapkan di layer Caddy sebelum request sampai ke Laravel.

## 4. ALUR SEKUENSIAL TORCH ENGINE (WAJIB DIIKUTI PERSIS)

```
1. NOC klik "Inspect" → POST /api/torch/inspect {router_id, username}
2. Backend: GET /system resource print → cek cpu-load
   - IF cpu-load > 80% → return 429 "Router CPU tinggi, Torch ditunda"
3. Backend: cek concurrency lock Redis (maks 2 sesi/router)
   - IF penuh → return 429 "Router sedang sibuk"
4. Backend: /ppp active print where name="{username}"
   - IF kosong → return 200 {status: "OFFLINE"}
5. Backend: simpan session_id_snapshot dari hasil step 4
6. Backend: mulai heartbeat lock Redis (TTL 10 detik)
7. Backend: JIKA waktu antara step 4 dan step 8 > 2 detik → BATALKAN, return "Sesi berubah, coba lagi"
8. Backend: re-validasi session_id masih sama dengan step 5
   - IF berbeda → BATALKAN, return "Sesi berubah, coba lagi"
9. Backend: mulai /tool torch dengan .tag unik, buka SSE stream ke frontend
10. Loop setiap 5 detik: refresh heartbeat lock TTL
11. Enrichment: port classification + GeoIP lookup + parallel ping/jitter (background)
12. IF connection_aborted() terdeteksi → kirim /cancel dengan .tag terkait, END
13. IF durasi mencapai 120 detik → paksa putus stream + /cancel, END
14. Watchdog cron (independen, tiap 15 detik): cek sesi RUNNING di DB tanpa lock Redis hidup
    → kirim /cancel paksa, update status FORCE_TERMINATED, log auto_cleanup=true
```

## 5. NON-FUNCTIONAL REQUIREMENTS

| Kategori | Requirement |
|---|---|
| **Performa** | Query cache user (Section 3, endpoint `/api/routers/{id}/users`) harus response < 50ms untuk 5.000 baris. |
| **Concurrency** | Maksimal 2 sesi Torch bersamaan per router (Redis lock), maksimal 5 router sync paralel (Section sync). |
| **Timeout** | Connection timeout API MikroTik: 5 detik. Session timeout Torch: 120 detik (hard limit, terpisah dari connection timeout). |
| **Retry Policy** | Maksimal 3x retry dengan exponential backoff (1s, 3s) untuk kegagalan API sementara; setelah itu circuit breaker aktif. |
| **Availability** | Router berstatus `DEGRADED` setelah 3x gagal sync berturut-turut; cooldown 15 menit sebelum retry berikutnya. |
| **RTO/RPO** | RTO restore cache database: ≤ 30 menit. RPO data user PPPoE: ≤ 1 jam. |
| **RouterOS Compatibility** | Wajib test matrix eksplisit untuk `/tool torch`, `/ppp active print`, `/cancel`, `/system resource print` di RouterOS v6.x dan v7.x sebelum rilis — tidak boleh diasumsikan identik. |

## 6. MATRIKS PENANGANAN ERROR

| Skenario | Response ke UI | Aksi Backend |
|---|---|---|
| Router unreachable saat force-sync | "Router tidak dapat dihubungi" | Tandai `consecutive_sync_failures + 1`, cek threshold circuit breaker |
| CPU router > 80% saat request Torch | "Router CPU tinggi (X%), Torch ditunda" | Tolak request, tidak retry otomatis |
| Session-id berubah di tengah validasi | "Sesi berubah, silakan coba lagi" | Batalkan eksekusi Torch, tidak lanjut ke MikroTik |
| Browser NOC menutup tab saat Torch aktif | (tidak ada, koneksi sudah putus) | `connection_aborted()` → kirim `/cancel` via `.tag` |
| Proses backend crash di tengah sesi Torch | (di luar kendali user) | Watchdog cron mendeteksi orphaned lock dalam ≤15 detik → paksa `/cancel` |
| Force-sync dipanggil > 1x dalam 5 menit | "Terlalu sering, tunggu X menit lagi" | Rate limit Redis, tolak request |
| Staf NOC ke-3 coba Torch di router yang sama | "Router sedang sibuk melayani troubleshooting lain" | Tolak, concurrency lock penuh |

## 7. DEFINITION OF DONE PER FASE

Detail acceptance criteria dan tahapan eksekusi lihat `AGENT.md` Section 4 (Roadmap Eksekusi Per Fase).

---
**Dokumen ini adalah spesifikasi teknis mengikat. Perubahan apapun terhadap skema database, endpoint, atau alur sekuensial di atas wajib didiskusikan ulang dengan pemilik produk sebelum diimplementasikan — tidak boleh diputuskan sepihak oleh AI agent di tengah development.**
