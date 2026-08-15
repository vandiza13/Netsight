#!/bin/bash
set -e

echo "================================================="
echo "       NETSIGHT ON-PREMISE INSTALLATION          "
echo "================================================="

# Pastikan script dijalankan sebagai root
if [ "$EUID" -ne 0 ]; then
  echo "Harap jalankan script ini sebagai root (Gunakan sudo)."
  exit 1
fi

# Pastikan Docker dan Docker Compose sudah terinstal
if ! command -v docker &> /dev/null; then
    echo "[!] Docker belum terinstal. Sedang menginstal Docker..."
    curl -fsSL https://get.docker.com -o get-docker.sh
    sh get-docker.sh
    rm get-docker.sh
fi

# Minta License Key jika tidak dipasangkan di argumen
LICENSE_KEY=$1
if [ -z "$LICENSE_KEY" ]; then
    read -p "Masukkan Kunci Lisensi (License Key) Netsight Anda: " LICENSE_KEY
fi

if [ -z "$LICENSE_KEY" ]; then
    echo "Instalasi dibatalkan: Kunci Lisensi wajib diisi."
    exit 1
fi

INSTALL_DIR="/opt/netsight"
echo "[1/4] Membuat direktori instalasi di $INSTALL_DIR..."
mkdir -p "$INSTALL_DIR"
cd "$INSTALL_DIR"

# Download Source Code / Installer (Di-host di VPS A)
# Di dalam tar.gz ini sudah berisi kode netsight-core yang di-obfuscate
DOWNLOAD_URL="https://install-netsight.vandiza.com/netsight-onpremise-latest.tar.gz"
echo "[2/4] Mengunduh paket Netsight terbaru..."
curl -sSL -o netsight.tar.gz "$DOWNLOAD_URL"

echo "[3/4] Mengekstrak file..."
tar -xzf netsight.tar.gz
rm netsight.tar.gz

echo "[4/4] Mengonfigurasi Environment dan Lisensi..."
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Memasukkan lisensi ke dalam file .env
echo "LICENSE_KEY=${LICENSE_KEY}" >> .env

# ====== TAMBAHAN WAJIB UNTUK SECRETS ======
echo "[4.5/5] Menyiapkan Kunci Keamanan (Secrets)..."
mkdir -p docker/secrets
cd docker/secrets
# Gunakan openssl untuk menggenerate password secara acak agar aman
if [ ! -f db_password ]; then
    openssl rand -base64 16 > db_password
fi
if [ ! -f redis_password ]; then
    openssl rand -base64 16 > redis_password
fi
if [ ! -f app_key ]; then
    echo "base64:$(openssl rand -base64 32)" > app_key
fi
cd ../..
# ==========================================

echo "[5/5] Memulai Layanan Netsight dengan Docker Compose..."
# Menggunakan docker-compose khusus onpremise
docker compose -f docker-compose.onpremise.yml up -d --build

echo "Menunggu database siap (10 detik)..."
sleep 10

echo "Menyiapkan Database dan Akun Admin Default..."
docker compose -f docker-compose.onpremise.yml exec -T app php artisan migrate --force
docker compose -f docker-compose.onpremise.yml exec -T app php artisan db:seed --class=TenantInitialSeeder --force

echo "================================================="
echo " NETSIGHT BERHASIL DIINSTAL!                     "
echo " Akses aplikasi Anda melalui browser:            "
echo " http://$(curl -s ifconfig.me)                   "
echo " Gunakan Akun Default:                           "
echo " Email: admin@netsight.xyz                       "
echo " Password: admin                                 "
echo "================================================="
