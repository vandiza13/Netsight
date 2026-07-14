# SECURITY.md — Kebijakan Keamanan NETSIGHT v2.1
**Dokumen ini berdiri sendiri dan mengikat untuk seluruh aspek keamanan sistem. Berlaku di atas asumsi "best practice" umum jika ada perbedaan.**

---

## 1. MODEL ANCAMAN (THREAT MODEL)

| Ancaman | Vektor | Dampak Jika Terjadi |
|---|---|---|
| Kredensial API MikroTik bocor | Database dump, backup tidak terenkripsi, `.env` ter-commit | Akses tak sah ke Core Router produksi |
| Command injection via parameter username | Input tidak tersanitasi diteruskan ke API MikroTik | Eksekusi command tak diinginkan di router |
| Session hijacking staf NOC | Token/cookie dicuri | Akses tak sah ke data traffic pelanggan |
| Insider threat — staf NOC Tier 1 mengakses data di luar kewenangan | Manipulasi request langsung (bypass UI) | Kebocoran data pribadi pelanggan (IP publik, traffic pattern) |
| Router crash akibat overload | Torch dijalankan tanpa guardrail, retry tanpa batas | Downtime layanan ISP ke pelanggan riil |
| Orphaned session (proses backend crash) | Backend mati saat sesi Torch aktif | Torch menyala tanpa kendali, membebani CPU router dalam waktu lama |

---

## 2. MANAJEMEN KREDENSIAL

### 2.1 Enkripsi at Rest
- Password API MikroTik **wajib** dienkripsi AES-256-CBC (`Crypt::encryptString()` Laravel) sebelum disimpan di kolom `credential_encrypted`.
- **Dilarang keras** menyimpan kredensial dalam bentuk plain text di database, log file, atau file konfigurasi apapun — termasuk saat debugging.

### 2.2 Key Management
- `APP_KEY` Laravel **tidak boleh** disimpan di file `.env` yang ikut ter-commit ke repository atau ter-backup bersama kode aplikasi.
- Gunakan Docker Secrets atau HashiCorp Vault untuk inject `APP_KEY` dan kredensial sensitif lainnya saat runtime.
- Rotasi `APP_KEY` mengikuti prosedur re-enkripsi seluruh data terenkripsi (tidak bisa rotasi tanpa migrasi data).

### 2.3 Rotasi Kredensial Router
- Password user API MikroTik wajib dirotasi otomatis setiap **90 hari**.
- Mekanisme *dual-credential*: kredensial lama tetap valid selama masa transisi (1 jam) untuk menghindari downtime saat rotasi berlangsung di banyak router.

### 2.4 Least Privilege
- User API MikroTik yang dipakai NETSIGHT **hanya** boleh memiliki permission: `api`, `read`, `test`.
- **Dilarang keras** memberi permission `write`, `policy`, atau `reboot` ke user ini, tanpa pengecualian.

### 2.5 Break-Glass Access
- Prosedur akses darurat tertulis wajib ada untuk skenario VPN internal down bersamaan dengan gangguan router.
- Akun break-glass terpisah, membutuhkan approval 2 admin, aktif manual, otomatis ter-log dan ter-nonaktifkan kembali setelah durasi terbatas (maksimal 4 jam).

---

## 3. AUTENTIKASI & OTORISASI

### 3.1 Autentikasi Staf NOC
- **Wajib** dua lapis: password konvensional + TOTP (Google Authenticator atau kompatibel).
- Password mengikuti kebijakan minimum: 12 karakter, kombinasi huruf besar/kecil/angka/simbol.
- Session token expired setelah 8 jam tidak aktif.

### 3.2 IP Whitelisting / VPN
- Endpoint login **wajib** diproteksi di layer Caddy — hanya menerima request dari IP Publik Kantor/NOC atau subnet VPN internal.
- Diterapkan **sebelum** request mencapai aplikasi Laravel (fail closed by default).

### 3.3 RBAC — Matriks Akses

| Resource | TIER_1 | TIER_2 | ADMIN |
|---|---|---|---|
| Ringkasan grafik kategori traffic | ✅ | ✅ | ✅ |
| Detail IP publik mentah | ❌ | ✅ | ✅ |
| Trigger Torch | ❌ | ✅ | ✅ |
| Winbox Grid Mode (deep-dive) | ❌ | ✅ | ✅ |
| Kelola kredensial router | ❌ | ❌ | ✅ |
| Kelola akun staf | ❌ | ❌ | ✅ |
| Lihat audit trail | ❌ | ❌ | ✅ |

**Penegakan wajib di layer backend (response API), bukan di frontend.** Uji otomatis harus membuktikan bahwa request langsung (curl/Postman) dari akun TIER_1 ke endpoint TIER_2 mengembalikan `403`, bukan data ter-redact di sisi client.

---

## 4. KEAMANAN KONEKSI ROUTER

### 4.1 Metode Koneksi (Pilih Salah Satu, Wajib Terenkripsi)
- **Utama:** Private VPN Routing (WireGuard/OpenVPN Site-to-Site), port API MikroTik classic (8728) tertutup total dari internet publik.
- **Alternatif:** API-SSL (port 8729) dengan sertifikat valid, jika backend berada di cloud publik terpisah.

### 4.2 Input Sanitization (Anti Command Injection)
- Username yang diteruskan sebagai parameter API MikroTik **wajib** melalui whitelist karakter (alfanumerik, titik, underscore, strip saja).
- Gunakan parameterized query builder bawaan `routeros-api-php` (`Query::where()`, `Query::equal()`) — **dilarang** membangun string command lewat concatenation manual.

### 4.3 Pre-flight Router Health Check
- Sebelum eksekusi Torch, wajib cek `/system resource print`.
- CPU load > 80% → tolak request. 60–80% → izinkan dengan warning. < 60% → normal.

---

## 5. GUARDRAIL OPERASIONAL (CRASH PREVENTION)

| Guardrail | Spesifikasi |
|---|---|
| Concurrency lock | Maksimal 2 sesi Torch bersamaan per router (Redis lock) |
| Hard session timeout | 120 detik, dipisah dari connection timeout (5 detik) |
| Heartbeat lock | TTL 10 detik, refresh tiap 5 detik selama sesi aktif |
| Watchdog cron | Tiap 15 detik, deteksi orphaned session, paksa `/cancel` |
| Circuit breaker sync | 3x gagal berturut-turut → `DEGRADED`, cooldown 15 menit |
| Retry API | Maksimal 3x, exponential backoff (1s, 3s), lalu berhenti |
| Rate limit force-sync | Maksimal 1x per 5 menit per router |

---

## 6. AUDIT & LOGGING

- Setiap aksi inspeksi (klik "Inspect") wajib tercatat: `[Timestamp] - [User Admin NOC] - [Aksi] - [Target Pelanggan] - [Nama Router]`.
- Sesi yang di-force-terminate oleh watchdog wajib ditandai `auto_cleanup: true` terpisah dari pembatalan manual, untuk investigasi pola kegagalan.
- Log audit **tidak boleh** bisa dihapus atau diedit oleh role manapun kecuali melalui prosedur arsip resmi (append-only di level aplikasi).
- Error tracking (Sentry) aktif sejak Fase 1 — tidak boleh ada error senyap di sistem yang menyentuh infrastruktur produksi.

---

## 7. KEAMANAN DEPENDENSI

- Semua dependency (Composer & NPM) wajib melalui `composer audit` / `npm audit` sebagai bagian dari CI/CD (GitHub Actions) sebelum merge.
- Dependency baru yang tidak tercantum di `PRD.md` Section 4 wajib dicatat alasannya di PR description.
- MaxMind GeoIP2 database (`.mmdb`) diperbarui berkala (bulanan) namun tetap disimpan lokal — **tidak boleh** query real-time ke API eksternal.

---

## 8. RENCANA RESPONS INSIDEN (RINGKAS)

| Skenario | Langkah Pertama |
|---|---|
| Kredensial router dicurigai bocor | Rotasi paksa kredensial router terkait segera, review audit log akses 90 hari terakhir |
| Router mengalami CPU spike tak terduga | Cek audit trail untuk sesi Torch aktif di waktu bersamaan, matikan sesi manual jika masih berjalan |
| Staf NOC dicurigai mengakses data di luar kewenangan | Nonaktifkan akun sementara, review audit trail lengkap staf terkait |
| Watchdog gagal membersihkan sesi orphaned | Eskalasi manual — jalankan `/cancel` langsung via Winbox, investigasi kenapa watchdog gagal |

---

## 9. SIKLUS REVIEW KEAMANAN

Dokumen ini wajib direview ulang setiap kali:
- Ada penambahan endpoint baru yang menyentuh data pelanggan atau kredensial router.
- Ada perubahan versi mayor pada Laravel, PostgreSQL, atau Redis (lihat `PRD.md` Section 4 untuk versi terverifikasi saat ini).
- Setelah insiden keamanan apapun, sekecil apapun dampaknya.

---
**Dokumen ini adalah bagian dari paket handoff `PRD.md` / `SRD.md` / `AGENT.md`. Semua developer dan AI agent yang bekerja pada proyek ini wajib mematuhi kebijakan di atas tanpa pengecualian, kecuali ada persetujuan tertulis dari pemilik produk untuk kasus spesifik.**
