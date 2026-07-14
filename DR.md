# NETSIGHT v2.1 — Disaster Recovery Plan (DRP)

Dokumen ini memandu tim Infrastruktur & NOC untuk memulihkan layanan **NETSIGHT v2.1** dalam skenario kegagalan bencana (*Disaster*). 

- **Recovery Time Objective (RTO):** ≤ 30 Menit
- **Recovery Point Objective (RPO):** ≤ 1 Jam

## 1. Lingkup Bencana
Skenario yang ditangani meliputi:
1. Kegagalan *Node* Server (VM Down / Hardware Failure).
2. Korupsi Database PostgreSQL.
3. Serangan *Ransomware* atau Kompromi Keamanan.

## 2. Arsitektur Data & Pencadangan (Backup)
Karena NETSIGHT v2.1 sebagian besar merupakan lapisan perantara (*middleware/cache*) antara sistem *Billing* ISP dan Router MikroTik, data yang paling kritis adalah:
1. **Audit Logs & Staff Credentials** (RPO: 1 Jam).
2. **Kredensial Router** (AES-256 Enkripsi).

Cadangan (*Backup*) dikonfigurasi melalui *Cronjob* menggunakan skrip `pg_dump` ke dalam *Amazon S3* (atau penyimpanan *Cold Storage* setara) dengan frekuensi **setiap 1 Jam**.
Data pengguna PPPoE (`pppoe_users_cache`) bersifat *ephemeral* dan dapat ditarik ulang secara otomatis.

---

## 3. Prosedur Pemulihan (RTO: 30 Menit)

### A. Persiapan Infrastruktur (5 Menit)
1. Siapkan VM baru (Ubuntu 22.04 LTS / Alpine) dengan konektivitas *Network* (IP Whitelist/VPN) yang identik.
2. Pasang prasyarat utama: Docker & Docker Compose.
3. Kloning repositori rilis produksi terakhir:
   ```bash
   git clone git@github.com:your-isp/netsight.git /opt/netsight
   cd /opt/netsight
   ```

### B. Pemulihan Variabel Lingkungan & Kredensial (5 Menit)
1. Pulihkan file `.env.prod` dari *Secret Manager* (AWS Secrets Manager / Vault / 1Password). 
2. Pulihkan file Docker Secrets:
   - `docker/secrets/db_password`
   - `docker/secrets/redis_password`
   - `docker/secrets/app_key`
   - `docker/secrets/grafana_password`
> **Kritis:** Pastikan `APP_KEY` yang digunakan **sama persis** dengan saat *backup* terakhir, karena hal ini krusial untuk mendekripsi sandi (AES-256) router MikroTik.

### C. Pemulihan Database (10 Menit)
1. Ambil berkas SQL _dump_ terakhir dari S3 (RPO ≤ 1 jam).
2. Hidupkan basis data PostgreSQL:
   ```bash
   docker-compose -f docker-compose.prod.yml up -d db
   ```
3. Impor data cadangan:
   ```bash
   cat netsight_backup_YYYYMMDD_HH.sql | docker exec -i netsight_db_1 psql -U netsight -d netsight
   ```

### D. Menghidupkan Layanan (5 Menit)
1. Luncurkan seluruh tumpukan aplikasi:
   ```bash
   docker-compose -f docker-compose.prod.yml up -d
   ```
2. Jalankan optimasi sistem:
   ```bash
   docker exec -it netsight_app_1 php artisan optimize:clear
   docker exec -it netsight_app_1 php artisan config:cache
   ```

### E. Sinkronisasi Ulang (*Warm-up Cache*) (5 Menit)
Kirim antrean pekerjaan (`Job`) untuk membangun ulang tabel `pppoe_users_cache` dari router secara manual tanpa perlu menunggu siklus *Cron*:
```bash
docker exec -it netsight_app_1 php artisan horizon:pause
docker exec -it netsight_app_1 php artisan netsight:sync-all
docker exec -it netsight_app_1 php artisan horizon:continue
```

## 4. Validasi Pemulihan
Setelah *stack* hidup, periksa kesehatan (*Healthcheck*):
1. **Frontend:** Akses HTTPS dashboard dan konfirmasi bisa masuk.
2. **Konektivitas:** Pastikan `Status` semua router berubah menjadi `HEALTHY`.
3. **Torch Engine:** Lakukan satu kali inspeksi Torch selama 10 detik dan hentikan; cek apakan log audit terekam dengan benar.
4. **Monitoring:** Akses `http://<server-ip>:3000` (Grafana) untuk memastikan Prometheus menerima metrik CPU & Memori dari *container* PHP.

---
**Persetujuan RTO/RPO:**
Rencana ini diklaim valid jika latihan bencana (*Disaster Drill*) diselenggarakan sekurang-kurangnya satu kali setiap kwartal (3 bulan).
