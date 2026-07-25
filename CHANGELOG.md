# CHANGELOG

Semua pembaruan pada aplikasi Netsight akan dicatat di file ini agar terstruktur dan mudah dilacak sebelum di-deploy ke Production.

## [Unreleased / Ready to Deploy] - 2026-07-25

### UI/UX & Tampilan Utama (Dashboard NOC)
- **[REMOVED]** Menghapus grafik *PppoeDistributionChart* dan *RouterHealthGrid* (kartu kotak-kotak) yang terlalu memakan tempat dan membingungkan secara visual.
- **[ADDED]** Menambahkan komponen baru `RouterHealthTable.vue`. Menampilkan seluruh status router dalam format tabel baris yang sangat padat informasi (Nama, IP, RouterOS, Status Koneksi 🟢🔴, Last Sync).
- **[CHANGED]** Merombak tata letak `DashboardPage.vue` menjadi sistem *Split Layout* 70/30:
  - 70% Layar Kiri: Tabel Monitoring Router.
  - 30% Layar Kanan: Log Aktivitas Sistem & Riwayat Diagnostik (Torch).
- **[FIXED]** Memperbaiki atribusi aktor di `ActivityFeed.vue`. Mengubah relasi dari `user` menjadi `staff_noc_id` (`log.staff?.name`) sehingga nama asli staf (misal: "Administrator" atau "Budi") muncul di log, bukan sekadar tulisan "System".
- **[CHANGED]** Menerjemahkan seluruh riwayat tindakan API mentah di `ActivityFeed.vue` menjadi Bahasa Indonesia yang cerdas dan kontekstual (contoh: "menghapus router BimaNet" atau "memantau aliran jaringan (Torch)").

### Fitur Visualisasi Interface Router (Backend & Frontend)
- **[ADDED]** Modul `RouterInterfaceViewer` dan desain *Metal Rack* realistis untuk melihat *port-port* fisik (RJ45 / SFP) beserta lampu LED indikator *link* menyala/mati secara visual.
- **[ADDED]** Tabel Database baru via migrasi: `2026_07_23_000001_create_router_interfaces_cache_table.php` untuk menyimpan *cache state interface* (mengurangi beban API MikroTik).
- **[ADDED]** Model `RouterInterfaceCache` pada modul internal `Netsight-core`.
- **[ADDED]** Penambahan *endpoint* khusus pada `RouterController.php` (di dalam `Netsight-core`) untuk mengambil pembaruan trafik antarmuka (*live traffic monitoring*) tanpa perlu me-refresh halaman.
- **[FIXED]** Memperbaiki sistem *fallback* pembacaan kecepatan jaringan untuk *port* berbasis serat optik SFP+ (10Gbps) di `MikrotikApiService.php`.
- **[CHANGED]** Pengelompokan tipe *interface* pada UI menjadi jauh lebih teratur: memisahkan antarmuka fisik (Ethernet/SFP), antarmuka Virtual (VLAN/Bonding/Bridge), dan Tunnel (VPN/PPPoE).
