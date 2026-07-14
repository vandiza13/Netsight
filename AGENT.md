# AGENT.md — Panduan Eksekusi untuk AI Coding Agent (Antigrafity)
## Project: NETSIGHT v2.1
**Dokumen ini adalah instruksi kerja langsung untukmu, Antigrafity. Baca sampai habis sebelum menulis baris kode pertama.**

---

## 0. ATURAN PALING PENTING (BACA DULUAN)

1. **Jangan pernah menyederhanakan atau melewati guardrail** di Section 5 PRD.md / Section 4 SRD.md demi mengejar kecepatan development. Sistem ini menyentuh router produksi 1.5 Gbps — bug di sini bukan cuma bug aplikasi, tapi bisa jadi insiden jaringan nyata.
2. **Jangan menembak API MikroTik secara live untuk kebutuhan yang bisa dipenuhi dari cache lokal.** Kalau kamu menemukan dirimu menulis kode yang query router setiap kali dashboard di-load, STOP — itu melanggar prinsip inti arsitektur ini.
3. **Jika ada ambiguitas antara dokumen ini dan asumsi umum "best practice" yang kamu tahu, dokumen `SRD.md` menang.** Dokumen ini sudah dirancang spesifik untuk risiko sistem ini, bukan aplikasi CRUD biasa.
4. **Fase 3 (Torch Engine + Guardrail) adalah fase paling kritis.** Jangan lanjut ke Fase 4 sebelum test simulasi crash backend di tengah sesi Torch (lihat Section 4 di bawah) benar-benar lulus.

---

## 1. STRUKTUR REPOSITORY

```
netsight/
├── app/
│   ├── Http/Controllers/Api/       # Controller per domain (RouterController, TorchController, dst)
│   ├── Services/
│   │   ├── MikrotikApiService.php  # Wrapper routeros-api-php, SEMUA panggilan API lewat sini
│   │   ├── TorchGuardrailService.php  # Heartbeat lock, circuit breaker, pre-flight check
│   │   ├── EnrichmentService.php   # Port classification, GeoIP lookup
│   │   └── SyncService.php         # Background sync logic
│   ├── Jobs/
│   │   ├── SyncRouterUsersJob.php
│   │   └── WatchdogOrphanedSessionJob.php  # Cron tiap 15 detik, lihat SRD.md Section 4 step 14
│   ├── Models/
│   └── Console/Commands/
├── config/
│   └── netsight.php  # threshold CPU, timeout, TTL lock — SEMUA angka guardrail taruh di sini, JANGAN hardcode di service
├── database/migrations/
├── resources/js/
│   ├── components/
│   ├── stores/         # Pinia stores
│   └── composables/    # useTorchStream.ts, useRouterStatus.ts, dll
├── tests/
│   ├── Feature/
│   │   ├── GuardrailTest.php       # WAJIB ADA — test orphaned session, circuit breaker, pre-flight CPU
│   │   └── RbacEnforcementTest.php # WAJIB ADA — test bahwa TIER_1 tidak bisa akses data sensitif via request manual
│   └── Unit/
├── docker-compose.yml
├── Dockerfile
├── PRD.md
├── SRD.md
├── AGENT.md
└── SECURITY.md
```

**Aturan penamaan:** PSR-12 untuk PHP, ESLint (Vue 3 recommended) untuk frontend. Nama service class deskriptif — hindari nama generik seperti `Helper.php` atau `Utils.php`.

---

## 2. KONVENSI KODE WAJIB

- **Semua angka guardrail** (threshold CPU 80%, TTL lock 10 detik, timeout 120 detik, rate limit 5 menit) **wajib** berada di `config/netsight.php`, bukan hardcoded di dalam service/controller. Ini supaya tim bisa tuning tanpa redeploy kode.
- **Semua panggilan ke MikroTik API wajib lewat `MikrotikApiService`**, tidak boleh instansiasi client routeros-api-php langsung di controller/job manapun. Ini penting untuk memastikan retry/circuit breaker logic konsisten di satu tempat.
- **Setiap fitur yang menyentuh guardrail (Section 5 PRD.md) wajib punya test Pest sebelum dianggap selesai** — bukan opsional, bukan "nanti ditest belakangan".
- **Commit message convention:** `[FASE-X] deskripsi singkat` (contoh: `[FASE-3] implementasi heartbeat lock TTL`).
- **Jangan menambahkan dependency baru** yang tidak tercantum di PRD.md Section 4 tanpa mencatat alasannya di commit message atau PR description.

---

## 3. CARA MENANGANI AMBIGUITAS

Jika kamu menemukan spesifikasi yang kurang jelas saat eksekusi:
1. Cek dulu apakah jawabannya ada di `SRD.md` (paling detail teknis) atau `SECURITY.md` (kalau menyangkut keamanan).
2. Jika benar-benar tidak ada, **pilih interpretasi yang paling konservatif terhadap keselamatan router** (contoh: kalau ragu apakah suatu request butuh pre-flight CPU check, jawabannya selalu YA).
3. Catat asumsi yang kamu ambil di komentar kode atau PR description — jangan diam-diam memutuskan sendiri tanpa jejak.

---

## 4. ROADMAP EKSEKUSI PER FASE (DENGAN ACCEPTANCE CRITERIA)

### FASE 1 — Fondasi & Keamanan
**Kerjakan:** Setup Docker Compose (Laravel 13, PostgreSQL 18, Redis 8.6, Caddy). Auth + TOTP MFA. IP Whitelist di Caddy. Enkripsi kredensial (AES-256-CBC + secret manager). RBAC middleware.
**Acceptance:** Test otomatis membuktikan TIER_1 mendapat `403` saat memanggil endpoint TIER_2, bukan cuma disembunyikan di UI. Login gagal tanpa TOTP valid.

### FASE 2 — Background Sync & Cache Layer
**Kerjakan:** Job Horizon untuk sync `/ppp secret print`, staggered scheduling, circuit breaker per router.
**Acceptance:** Simulasikan 1 router mati — pastikan setelah 3x gagal, status jadi `DEGRADED` dan tidak ditembak lagi selama cooldown 15 menit (verifikasi lewat test, bukan manual).

### FASE 3 — Torch Engine Inti + Guardrail Kritis ⚠️ PRIORITAS TERTINGGI
**Kerjakan:** Ikuti alur sekuensial persis dari `SRD.md` Section 4. Heartbeat lock, watchdog cron, pre-flight CPU check, re-validasi session-id, SSE dengan `connection_aborted()`.
**Acceptance (WAJIB, tidak boleh diskip):**
- Simulasi `kill -9` pada proses backend di tengah sesi Torch aktif → watchdog harus mengirim `/cancel` dalam ≤ 15 detik tanpa intervensi manual.
- Simulasi CPU router > 80% → request Torch baru harus ditolak dengan pesan jelas.
- Simulasi user disconnect-reconnect tepat di jendela 2 detik antara deteksi dan eksekusi → sistem harus membatalkan, bukan menampilkan data salah target.

### FASE 4 — Data Enrichment & Visualisasi
**Kerjakan:** Port classification, GeoIP lokal, parallel ping/jitter, dashboard Vue 3 (Speedometer, Pie Chart, rule engine diagnostic).
**Acceptance:** Data yang tampil di UI konsisten dengan raw output Torch; update real-time tanpa reload halaman.

### FASE 5 — Deep-Dive Mode & Audit
**Kerjakan:** Winbox Grid Mode, audit trail lengkap, integrasi Sentry.
**Acceptance:** Setiap klik "Inspect" tercatat sesuai format `[Timestamp] - [User] - [Aksi] - [Target] - [Router]`.

### FASE 6 — Hardening Final, Load Test & DR
**Kerjakan:** Load test 2 sesi Torch paralel 120 detik di staging, simulasi disaster recovery, setup Prometheus + Grafana.
**Acceptance:** RTO ≤ 30 menit, RPO ≤ 1 jam, terverifikasi lewat drill nyata.

---

## 5. LARANGAN TEGAS

- **Jangan** memberi user API MikroTik izin `write`, `policy`, atau `reboot` — hanya `api, read, test`.
- **Jangan** menyimpan kredensial router dalam bentuk plain text, bahkan sementara saat debugging (gunakan `.env.example` dengan placeholder, jangan commit kredensial asli sama sekali).
- **Jangan** retry koneksi API MikroTik tanpa batas — maksimal 3x dengan exponential backoff, lalu sirkuit breaker.
- **Jangan** izinkan lebih dari 2 sesi Torch bersamaan per router, dalam kondisi apapun, meski untuk keperluan testing internal.
- **Jangan** membuat endpoint baru yang mem-bypass middleware RBAC "untuk sementara" — kalau butuh endpoint debug, tetap lewat middleware yang sama.

---

## 6. KONTAK ESKALASI

Jika di tengah eksekusi kamu menemukan kontradiksi teknis nyata antara dokumen (bukan ambiguitas kecil, tapi benar-benar bertentangan), hentikan eksekusi fase terkait dan catat di PR description untuk direview manusia sebelum lanjut — jangan menebak dan melanjutkan begitu saja untuk keputusan yang berdampak ke keamanan router produksi.
