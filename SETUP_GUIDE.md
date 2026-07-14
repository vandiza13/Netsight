# Panduan Pengaturan (Setup Guide) NETSIGHT v2.1

Panduan ini menjelaskan langkah demi langkah untuk menghubungkan **NETSIGHT v2.1** ke Router MikroTik fisik/asli Anda, mensinkronisasikan user PPPoE, serta menjalankan fitur **Live Torch Inspection** dan **Diagnostic Assistant**.

---

## Bagian 1: Persiapan di Router MikroTik

Agar NETSIGHT dapat berkomunikasi dengan MikroTik Anda melalui API-SSL, lakukan konfigurasi berikut pada sisi MikroTik:

### 1. Aktifkan Service API / API-SSL
Secara default, NETSIGHT mewajibkan penggunaan port aman **API-SSL (8729)** untuk mengamankan pengiriman kata sandi router.
1. Masuk ke MikroTik menggunakan Winbox.
2. Buka menu **IP** -> **Services**.
3. Pastikan service `api-ssl` aktif (berwarna hijau). Port standarnya adalah `8729`.
4. *(Opsional)* Jika Anda belum memiliki sertifikat SSL di MikroTik, Anda dapat menggunakan port `api` standar (`8728`) sementara waktu untuk pengujian lokal, namun pastikan untuk menyesuaikannya di pengisian data router nanti.

### 2. Buat User API Khusus
Buat pengguna (*user*) di MikroTik khusus untuk aplikasi NETSIGHT dengan hak akses minimal yang dibutuhkan untuk memantau dan menjalankan Torch.
1. Buka menu **System** -> **Users**.
2. Klik tab **Groups**, buat group baru (contoh: `netsight-group`) dengan memberikan centang pada kebijakan (*policy*) berikut:
   - **read** (untuk melihat user PPPoE aktif dan resource CPU)
   - **test** (diwajibkan oleh MikroTik untuk menjalankan perintah `/tool torch`)
   - **api** (diwajibkan untuk koneksi API)
3. Buka tab **Users**, buat user baru (contoh: `netsight-api`).
4. Masukkan password yang kuat, pilih group `netsight-group` yang telah dibuat tadi.

---

## Bagian 2: Konfigurasi & Inisialisasi Aplikasi

### 1. Pengaturan File `.env`
Buka file `.env` di direktori proyek `netsight` Anda, pastikan konfigurasi database dan Redis sudah benar. Contoh konfigurasi database:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=netsight
DB_USERNAME=postgres
DB_PASSWORD=your_postgres_password

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### 3. Jalankan Migrasi Database & Seeder
Buat skema tabel dan buat akun Admin pertama untuk masuk ke sistem:
```bash
# Jalankan migrasi database
php artisan migrate

# Jalankan seeder untuk membuat akun uji coba & Admin default
php artisan db:seed
```
*Catatan: Secara default, seeder akan membuat akun admin pertama:*
- **Email:** `admin@netsight.local` (atau periksa file `DatabaseSeeder.php` untuk akun yang tersedia).
- **Password:** `password` atau `password123`.

### 4. Dapatkan Kunci TOTP (MFA)
Karena login NETSIGHT dilindungi oleh Otentikasi Dua Faktor (TOTP/MFA):
- Pada lingkungan pengembangan (*development*), Anda dapat membuka basis data Anda di tabel `staff_noc` untuk melihat kolom `totp_secret_encrypted`.
- Untuk kenyamanan uji coba pertama kali, database seeder biasanya menetapkan kunci rahasia TOTP statis. Masukkan rahasia tersebut ke aplikasi authenticator Anda (seperti Google Authenticator, Microsoft Authenticator, atau Bitwarden) untuk menghasilkan kode 6 digit setiap kali login.

---

## Bagian 3: Jalankan Layanan Aplikasi

Buka terminal/command prompt di direktori proyek Anda dan jalankan komponen-komponen berikut:

### 1. Jalankan Server Backend Laravel
```bash
php artisan serve
```
Aplikasi backend sekarang berjalan di `http://127.0.0.1:8000`.

### 2. Jalankan Queue Worker (Laravel Horizon)
Horizon diperlukan untuk memproses sinkronisasi data user PPPoE dari MikroTik secara berkala di latar belakang.
```bash
php artisan horizon
```

### 3. Jalankan Server Frontend Vite
Buka terminal baru di direktori proyek Anda:
```bash
npm run dev
```
Aplikasi frontend siap diakses melalui browser di alamat yang diberikan (biasanya `http://localhost:5173` atau `http://localhost:3000`).

---

## Bagian 4: Menghubungkan MikroTik lewat Dashboard

1. Buka browser dan arahkan ke alamat frontend (misal: `http://localhost:5173`).
2. Login menggunakan email dan password Admin yang telah disiapkan di Bagian 2.
3. Masukkan kode 6 digit dari aplikasi Authenticator Anda.
4. Setelah masuk ke Dashboard, klik menu **Routers** 🖧 di sidebar.
5. Klik **+ Add Router**, isi formulir:
   - **Router Name:** Nama pengenal router (contoh: `Edge-Router-01`). *PENTING: Nama ini harus sama dengan nama user API yang Anda buat di MikroTik di Bagian 1.*
   - **Host/IP:** Alamat IP publik atau IP lokal MikroTik Anda yang dapat dijangkau dari server aplikasi.
   - **API Port:** Isi `8729` jika menggunakan API-SSL (direkomendasikan), atau `8728` jika menggunakan API standar.
   - **API Password:** Password dari user MikroTik yang Anda buat di Bagian 1.
   - **Sync Offset:** Jeda waktu sinkronisasi otomatis (default: `0`).
6. Klik **Simpan**.
7. Router baru Anda akan muncul di tabel dengan status **HEALTHY** jika koneksi berhasil dilakukan.

---

## Bagian 5: Menguji Fitur Torch & Monitoring Traffic User

### 1. Sinkronisasi Data PPPoE (Force Sync)
Secara terjadwal, aplikasi akan menyinkronkan data user PPPoE dari MikroTik. Namun, untuk mempercepat uji coba:
1. Kembali ke halaman **Dashboard** 📊.
2. Pilih Router yang baru Anda tambahkan pada daftar router di sebelah kiri.
3. Di panel sebelah kanan (bagian daftar user), klik tombol **Force Sync** 🔄 di pojok kanan atas.
4. Tunggu beberapa detik, maka daftar pengguna PPPoE aktif dari MikroTik Anda akan muncul di tabel.

### 2. Memulai Live Inspection (Torch)
1. Cari username PPPoE aktif yang ingin Anda pantau di kotak pencarian "Search by username...".
2. Pada baris user tersebut, klik tombol aksi **Inspect (🔍)** di kolom paling kanan.
3. Jendela melayang (*modal*) **Live Inspection** akan muncul.
4. Aplikasi akan otomatis:
   - Membuka SSE stream ke router.
   - Menampilkan aliran data paket real-time (Source IP, Destination IP + GeoIP, Port + Service, Protocol, Tx/Rx rate).
   - Menghasilkan diagram lingkaran/bar distribusi trafik di panel kanan.
   - Melakukan ping secara otomatis di balik layar ke IP PPPoE pelanggan.

### 3. Menguji Asisten Diagnostik (NOC Diagnostic Assistant)
Perhatikan kotak **Diagnostic Assistant** di panel kanan:
- **Kondisi Normal:** Jika latensi ping rendah dan pemakaian bandwidth stabil, sistem akan menampilkan ikon hijau 🟢 dan status "Koneksi normal".
- **Kondisi Bandwidth Penuh (Maxed Out):** Coba lakukan download/speedtest besar-besaran dari perangkat pelanggan tersebut. Indikator akan berubah menjadi merah 🔴 disertai peringatan bahwa bandwidth jenuh.
- **Kondisi Latensi Tinggi (High Latency):** Jika koneksi fisik pelanggan bermasalah (redaman kabel optik buruk), ping akan naik melampaui 100ms dan indikator berubah menjadi kuning 🟡 "Ping pelanggan tinggi".
- **Kondisi Putus Koneksi (RTO):** Jika modem pelanggan dimatikan atau kabel dicabut, sistem akan mendeteksi RTO dan menampilkan status merah 🔴 "Router gagal melakukan ping ke pelanggan".

---

## Penyelesaian Masalah (Troubleshooting)

- **Status Router UNREACHABLE:** Pastikan IP router dapat di-ping dari komputer/server Anda, port `8729` (atau `8728`) tidak diblokir oleh firewall MikroTik, dan service API/API-SSL di MikroTik telah diaktifkan.
- **Error "cpu-load high / router busy":** Sesuai standar keamanan di `SRD.md`, NETSIGHT membatasi maksimal 2 sesi Torch simultan per router, dan menolak Torch jika beban CPU router Anda sedang berada di atas 80% untuk melindungi stabilitas router produksi.
- **Daftar User PPPoE Kosong setelah Sync:** Pastikan di router MikroTik Anda memang memiliki user PPPoE aktif di menu **/interface pppoe-server active-print**.
