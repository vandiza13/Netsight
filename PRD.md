# PRD: NETSIGHT v2.1
## NOC Dynamic Traffic & Latency Inspection System
**Status:** Ready for Execution | **Target Eksekutor:** Tim/AI Antigrafity
**Dokumen terkait:** `SRD.md` (spesifikasi teknis), `AGENT.md` (panduan eksekusi AI agent), `SECURITY.md` (kebijakan keamanan)

---

## 1. LATAR BELAKANG & MASALAH

Tim NOC saat ini kesulitan mendiagnosis keluhan "internet lemot" pelanggan PPPoE secara cepat karena tidak ada visibilitas langsung ke traffic dan latency tanpa masuk manual ke Winbox per router. Proses manual ini lambat, tidak konsisten antar staf, dan berisiko human error saat berinteraksi langsung dengan Core Router produksi (1.5 Gbps backbone).

## 2. TUJUAN PRODUK

1. Memberi staf NOC visibilitas instan (0–5ms) terhadap data user PPPoE tanpa membebani router.
2. Menyediakan mekanisme verifikasi traffic live (Torch) yang aman, terbatas, dan tidak bisa menyebabkan router crash.
3. Memberi kesimpulan diagnosa otomatis ("kenapa lemot") agar staf NOC junior sekalipun bisa memberi rekomendasi tepat ke pelanggan.
4. Menjamin setiap aksi staf NOC tercatat lengkap untuk audit.

## 3. PERSONA & PERAN

| Persona | Kebutuhan | Batasan Akses |
|---|---|---|
| **NOC Tier 1 (Junior)** | Lihat ringkasan grafik kategori traffic pelanggan | Tidak boleh lihat IP publik mentah, tidak ada tombol aksi |
| **NOC Tier 2 / Network Engineer** | Torch, analitik penuh, detail IP tujuan | Akses penuh fitur inspeksi |
| **Administrator** | Kelola kredensial router, akun staf, audit trail | Akses penuh + manajemen sistem |

## 4. STACK TEKNOLOGI FINAL (TERVERIFIKASI)

| Layer | Teknologi & Versi | Catatan Verifikasi |
|---|---|---|
| Backend | **Laravel 13** (PHP 8.3+) | Rilis 17 Maret 2026, zero breaking changes dari v12, wajib PHP 8.3+ |
| Queue Worker | **Laravel Horizon** | Kompatibel penuh dengan Laravel 13, wajib Redis |
| Realtime Torch | **Server-Sent Events** (native PHP) | One-way stream, mendukung `connection_aborted()` |
| Realtime Notifikasi Dashboard | **Laravel Reverb** | Gunakan **Redis scaling driver** (bukan database driver bawaan v13) agar konsisten dengan backend Redis yang sudah wajib ada untuk guardrail |
| Cache/Lock/Circuit Breaker | **Redis 8.6** | GA Maret 2026, peningkatan throughput & reliability event-stream |
| Database Utama | **PostgreSQL 18** | Versi stable saat ini; PostgreSQL 19 masih Beta, belum untuk produksi |
| MikroTik API Client | **evilfreelancer/routeros-api-php v1.7.1** | Aktif maintained, native socket API-SSL, dukungan raw streaming reply. *(Catatan: tim disarankan evaluasi proof-of-concept SDK Fiber-based yang mendukung tag-multiplexing native sebelum Fase 3, karena match langsung dengan kebutuhan `.tag` correlation di guardrail — lihat SRD.md Section 5)* |
| Frontend | **Vue 3 (Composition API) + TypeScript + Vite** | Livewire dieliminasi (server-driven, tidak cocok streaming frekuensi tinggi) |
| Realtime Chart | **ApexCharts** via `vue3-apexcharts` | Native mendukung update streaming tanpa re-render penuh |
| State Management | **Pinia** | Standar resmi Vue 3 |
| GeoIP | **MaxMind GeoIP2 PHP** (database lokal `.mmdb`) | Wajib offline, tidak boleh API eksternal |
| Secret Management | **Docker Secrets / HashiCorp Vault** | `.env` statis dilarang untuk kredensial router |
| Reverse Proxy | **Caddy** | Automatic HTTPS |
| Containerization | **Docker + Docker Compose** | |
| Testing | **Pest PHP** (backend) / **Vitest** (frontend) | |
| Error Tracking | **Sentry** | Wajib sejak Fase 1 |
| Monitoring Historis | **Prometheus + Grafana** | Untuk histori CPU load router |
| CI/CD | **GitHub Actions** | Test wajib lulus sebelum merge |

## 5. FITUR UTAMA (RINGKASAN — DETAIL TEKNIS DI SRD.md)

1. **Hybrid Cache Dashboard** — daftar user PPPoE dari cache lokal, sync berkala per router (staggered).
2. **On-Demand Torch Inspection** — verifikasi live sesi pelanggan, dilindungi guardrail berlapis.
3. **NOC Diagnostic Assistant** — rule engine otomatis kesimpulan "kenapa lemot" berdasarkan bandwidth, jumlah koneksi, dan kualitas ping.
4. **Deep-Dive Winbox Grid Mode** — tabel investigasi lanjutan untuk Senior Engineer.
5. **RBAC & Audit Trail** — kontrol akses granular dan pencatatan lengkap tiap aksi.

## 6. ROADMAP FASE (LIHAT SRD.md UNTUK ACCEPTANCE CRITERIA TEKNIS LENGKAP)

| Fase | Fokus | Prioritas |
|---|---|---|
| 1 | Fondasi & Keamanan (auth, MFA, RBAC, enkripsi kredensial) | Wajib selesai duluan, tidak boleh diskip |
| 2 | Background Sync & Cache Layer | |
| 3 | Torch Engine Inti + Guardrail Kritis (heartbeat lock, watchdog) | **Prioritas tertinggi** |
| 4 | Data Enrichment & Visualisasi | |
| 5 | Deep-Dive Mode & Audit | |
| 6 | Hardening Final, Load Test & Disaster Recovery | |

## 7. METRIK KEBERHASILAN

- Waktu rata-rata staf NOC mendapat kesimpulan diagnosa: **< 3 menit** dari sebelumnya bisa >15 menit manual.
- Zero insiden router crash/overload akibat penggunaan sistem selama 3 bulan pertama pasca go-live.
- 100% aksi inspeksi tercatat di audit trail, dapat diverifikasi silang dengan log router.

## 8. DI LUAR CAKUPAN (OUT OF SCOPE) v2.1

- Manajemen konfigurasi router (write access) — sistem ini read-only + test-only terhadap router.
- Billing/invoicing pelanggan.
- Integrasi WhatsApp/notifikasi ke pelanggan langsung (hanya internal NOC).

---
**Dokumen ini adalah bagian dari paket handoff lengkap. Untuk detail implementasi teknis, ikuti `SRD.md`. Untuk cara kerja dan aturan main AI agent, ikuti `AGENT.md`. Untuk kebijakan keamanan mendalam, ikuti `SECURITY.md`.**
