# 🚀 Netsight Product Roadmap & Execution Strategy

Dokumen ini mencatat prioritas pengembangan sistem **Netsight** secara bertahap.

---

## 🥇 TAHAP 1 (PRIORITAS PERTAMA): Live Traffic Router via SNMP
> **Status:** ✅ **SELESAI & STABIL**

### Focus & Rationale:
- **Standar OID Internasional (IF-MIB):** Menggunakan `ifHCInOctets` (`.1.3.6.1.2.1.31.1.1.1.6`) dan `ifHCOutOctets` (`.1.3.6.1.2.1.31.1.1.1.10`) yang universal untuk semua router/MikroTik.
- **SNMP Engine Core:** Membangun fondasi engine SNMP versi 1 (polling, caching Redis/Cache, kalkulasi Mbps deltas).
- **Zabbix-Style History & Trends:** Menyimpan data mentah 10-detikan selama 24 jam dan agregasi 5-menitan hingga 30 hari.
- **Live Traffic Dashboard:** Grafik real-time tanpa membebani CPU MikroTik.

---

## 🥈 TAHAP 2 (PRIORITAS KEDUA): Integrasi Fungsi OLT via SNMP
> **Status:** ⏳ **NEXT TASK (PRIORITAS SAAT INI)**

### Focus & Rationale:
- **Leverage Engine Tahap 1:** Memanfaatkan infrastruktur & mesin SNMP yang sudah stabil dari Tahap 1.
- **OLT Management:** Fokus pada pemantauan perangkat OLT (EPON / GPON).
- **Redaman Optik (dBm) & Status ONU:** Kalkulasi RX/TX optical power, status online/offline ONU/ONT, dan distance.
- **Profile OID & Debugger Tool:** Membuat konfigurasi Profile JSON/YAML untuk berbagai merk OLT (HSan, VSOL, ZTE, Huawei, dll.) serta fitur **OLT OID Debugger Tool**.

---

## 🥉 TAHAP 3 (PRIORITAS KETIGA): Integrasi ACS dengan GenieACS (TR-069)
> **Status:** 🔮 **MENDATANG**

### Focus & Rationale:
- **Protokol TR-069 via REST API NBI:** Berkomunikasi dengan GenieACS Northbound Interface (NBI) menggunakan HTTP/JSON.
- **Infrastruktur Perangkat Tambahan:** Menambahkan container GenieACS (Node.js + MongoDB) tersendiri di Docker VPS.
- **CPE Management (Remote Modem):** Fitur remote management modem pelanggan dari jauh (ganti SSID WiFi, ganti password, reboot modem, cek status optical power per-user).

---

## 🗺️ Summary Flow:

```text
[Tahap 1: Live Traffic SNMP] ➡️ [Tahap 2: Integrasi OLT SNMP] ➡️ [Tahap 3: GenieACS TR-069]
   (Core SNMP Engine) ✅         (Redaman Optik OLT) ⏳            (Remote Management Modem) 🔮
```
