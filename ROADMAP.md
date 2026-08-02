# 🚀 Netsight Product Roadmap & Execution Strategy

Dokumen ini mencatat prioritas pengembangan sistem **Netsight** secara bertahap, terukur, dan dilengkapi strategi mitigasi risiko (*fallback plan*).

---

## 🥇 TAHAP 1: Live Traffic Router via SNMP
> **Status:** ✅ **SELESAI & STABIL**

### Focus & Deliverables:
- **Standar OID Internasional (IF-MIB):** Menggunakan `ifHCInOctets` (`.1.3.6.1.2.1.31.1.1.1.6`) dan `ifHCOutOctets` (`.1.3.6.1.2.1.31.1.1.1.10`) universal untuk semua router/MikroTik.
- **SNMP Engine Core:** Fondasi engine SNMP (polling, caching Redis, kalkulasi Mbps deltas).
- **Zabbix-Style History & Trends:** Menyimpan data mentah 10-detikan (24 jam) dan agregasi 5-menitan (30 hari).
- **Live Traffic Dashboard:** Grafik real-time tanpa membebani CPU MikroTik.

---

## 🥈 TAHAP 2: Integrasi Fungsi OLT via SNMP
> **Status:** ✅ **SELESAI & STABIL**

### Focus & Deliverables:
- **OLT Management & Multi-Vendor Profile:** Pemantauan perangkat OLT (EPON / GPON) dengan 9 profil preset OID (HiOSO, HiOSO v2 / C-Data, V-SOL EPON/GPON, HSan, BDCOM, ZTE C300/C320, Huawei MA5608T/MA5800, & Custom OID).
- **Redaman Optik (dBm) & Status ONU:** Kalkulasi RX/TX optical power, status online/offline/LOS ONU/ONT, dan jarak (distance meters).
- **Auto-Matching PPPoE:** Matching otomatis ONU ke pelanggan PPPoE berdasarkan MAC Address & Description.
- **OLT OID Debugger Tool & Signal History:** Fitur pemindai OID live dan grafik riwayat redaman optik 7 hari.

---

## 🥉 TAHAP 3: Integrasi ACS dengan GenieACS (TR-069) — SaaS Multi-Tenant Ready
> **Status:** ✅ **SELESAI & STABIL**

### 🎯 Sub-Fase Eksekusi Bertahap:

#### **Fase 3.1: Setup Infrastruktur Docker & Custom Port 9090**
- Menambahkan container `mongodb:6-alpine` (limit RAM 256M) dan `genieacs` di `docker-compose.yml`.
- Mengkonfigurasi Port CWMP Publik **`9090`** (anti-blokir ISP upstream) untuk ACS URL modem pelanggan (`http://subdomain-tenant:9090/`).
- Mengisolasi NBI REST API Port `7557` secara **100% Privat** di dalam jaringan Docker internal dengan proteksi `x-api-key`.

#### **Fase 3.2: Core Package Backend & Multi-Vendor Abstraction**
- Membuat migrasi database `acs_devices` (cache data modem per tenant).
- Membuat `GenieAcsService.php` untuk komunikasi NBI API aman (`urlencode($deviceId)`).
- Menyiapkan **Virtual Parameters (`vparams`)** untuk menstandardisasi beda parameter ZTE, Huawei, FiberHome, VSOL, dll. (`OpticalRxPower`, `WifiSSID`, `WifiPassword`).
- Menyiapkan **Simple Network ACS Credentials** (`netsight` / `netsight123`).

#### **Fase 3.3: Frontend UI Netsight & Pinia Store**
- Menambahkan modul menu **"Modem ACS (TR-069)"** di Sidebar Navigation Netsight (`/acs`).
- Membuat `AcsManagementPage.vue` & `AcsControlModal.vue` dengan 100% standar komponen UI/UX Netsight (Stat Cards, Dense Table, Dark Glassmorphism Modal).
- Menambahkan shortcut tombol pintas **"Modem ACS 🛜"** di tabel ONU halaman OLT.

#### **Fase 3.4: Asynchronous Polling & Testing**
- Penanganan response `202 Accepted` NBI API via Pinia polling agar UI tidak menggantung saat handshake TR-069.
- Pengujian isolasi multi-tenant SaaS & penanganan error.

---

## 🛡️ STRATEGI FALLBACK & MITIGASI RISIKO (PRODUCTION SAFETY)

| Skenario Risiko | Dampak Potensial | Strategi Fallback & Solusi Sistem |
| :--- | :--- | :--- |
| **1. GenieACS Service Down / Maintenance** | Akses kontrol modem terhenti. | **Netsight Isolation Shield:** Kegagalan service ACS tidak akan mengganggu fungsi Router & OLT. UI Netsight akan menampilkan status graceful *"ACS Service Standby"* tanpa memicu crash aplikasi. |
| **2. Firmware ONT Mengunci Credential / WiFi (Fault 9005/9007)** | Perubahan password via TR-069 ditolak modem. | **Graceful Fault Fallback:** Sistem akan menangkap SOAP Fault dengan aman dan otomatis memakai *Global Network Credentials* (`netsight`/`netsight123`) tanpa membuat koneksi modem terputus (*anti-deadlock*). |
| **3. Modem Dibelakang NAT Gagal Connection Request** | Pemicuan instan ACS ke modem timeout. | **Asynchronous Queue Fallback:** Jika trigger port 9090 publik ke ONT timeout di-NAT, task tetap tersimpan aman di queue GenieACS dan otomatis dieksekusi saat `PeriodicInform` modem (interval 300s) tiba. |
| **4. Lonjakan Memory VPS B (2GB RAM)** | Potensi Out of Memory (OOM). | **Strict Container Limits:** MongoDB dibatasi 256MB (`wiredTigerCacheSizeGB: 0.25`), Redis 96MB, GenieACS 200MB. Sisa RAM 800MB+ dijamin aman untuk sistem. |

---

## 🗺️ Summary Flow:

```text
[Tahap 1: Live Traffic SNMP] ➡️ [Tahap 2: Integrasi OLT SNMP] ➡️ [Tahap 3: GenieACS TR-069 SaaS]
   (Core SNMP Engine) ✅         (Redaman Optik OLT) ✅           (Remote Management Modem) ✅
                                                                  ├── 3.1: Docker & Port 9090 ✅
                                                                  ├── 3.2: Core Backend & vparams ✅
                                                                  ├── 3.3: UI Netsight & Modal ✅
                                                                  └── 3.4: Testing & Fallback Safe ✅
```
