# 🏗️ Netsight Project Architecture & Development Guide

**PENTING UNTUK DEVELOPER/AI:**  
Aplikasi ini memiliki arsitektur yang terbagi menjadi dua repositori Git terpisah. Kegagalan memahami struktur ini akan menyebabkan *commit* atau *push* tidak masuk ke tempat yang semestinya, sehingga fitur tampak gagal diterapkan.

## 📁 Struktur Repositori Terpisah

Aplikasi utama (`netsight`) dan *core package* (`netsight-core`) dikelola secara independen:

### 1. 🌐 Repositori Utama (`Netsight`)
- **Lokasi:** Root folder (`/`)
- **Fokus:** 
  - Frontend (Vue, Tailwind, Komponen UI, CSS, assets SVG/Images).
  - Konfigurasi aplikasi Laravel (Routes web, bootstrap, Vite config).
- **Aturan Git:** 
  - File dalam direktori `packages/` **diabaikan (ignored)** oleh `.gitignore`.
  - Melakukan `git commit` dan `git push` di direktori root **HANYA** akan menyimpan perubahan UI/Frontend dan *route* luar, **TIDAK** menyentuh perubahan *backend core*.

### 2. ⚙️ Repositori Core (`Netsight-core`)
- **Lokasi:** `packages/vandiza/netsight-core/`
- **Fokus:** 
  - Backend Logic (Controllers, Models, Database Migrations, Services, API Endpoints, Middleware).
- **Aturan Git:** 
  - Ini adalah repositori Git mandiri (`vandiza13/Netsight-core.git`).
  - **Setiap modifikasi backend (Controller, Model, API, dll) HARUS di-commit dan di-push dari dalam direktori ini.**
  - `cd packages/vandiza/netsight-core` -> `git add .` -> `git commit` -> `git push`.

---

## 🚦 Alur Kerja Wajib (Workflow)

Jika Anda (Developer atau AI Assistant) diminta untuk membuat fitur *Full-Stack* (Backend + Frontend):

1. **Ubah Backend:** Lakukan perubahan pada Model, Controller, atau Service di dalam `packages/vandiza/netsight-core`.
2. **Push Backend:** 
   ```bash
   cd packages/vandiza/netsight-core
   git add .
   git commit -m "Backend: ..."
   git push
   cd ../../../
   ```
3. **Ubah Frontend:** Lakukan perubahan pada UI, Vue Components, atau Store di direktori utama `resources/js/...`.
4. **Push Frontend:** 
   ```bash
   git add .
   git commit -m "UI: ..."
   git push
   ```

**JANGAN** menggabungkan kedua *push* tersebut dengan harapan root direktori akan mendeteksi perubahan pada `packages/`. Selalu periksa `git status` secara terpisah di kedua direktori tersebut!
