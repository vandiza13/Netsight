@echo off
setlocal enabledelayedexpansion

echo ===================================================
echo     NETSIGHT ON-PREMISE BUILD SCRIPT (IONCUBE)
echo ===================================================

set "SOURCE_DIR=%CD%"
set "BUILD_DIR=%CD%\..\netsight-onpremise-build"
set "ZIP_FILE=%CD%\..\netsight-onpremise-release.zip"

echo [1/5] Membersihkan folder build lama...
if exist "%BUILD_DIR%" rmdir /s /q "%BUILD_DIR%"
if exist "%ZIP_FILE%" del /q "%ZIP_FILE%"

echo [2/5] Menyalin proyek ke folder build...
:: Menggunakan robocopy agar cepat dan bisa mem-filter folder yang tidak perlu (seperti .git, node_modules)
robocopy "%SOURCE_DIR%" "%BUILD_DIR%" /E /XD .git node_modules vendor storage\logs storage\framework\cache /XF .env .env.example *.zip *.tar.gz
:: Robocopy mengembalikan exit code < 8 jika sukses
if %ERRORLEVEL% GEQ 8 (
    echo [ERROR] Gagal menyalin file proyek.
    exit /b %ERRORLEVEL%
)

echo [3/5] Menghapus file sensitif tambahan...
if exist "%BUILD_DIR%\storage\keys\private.key" del /q "%BUILD_DIR%\storage\keys\private.key"
if exist "%BUILD_DIR%\storage\keys\public.key" del /q "%BUILD_DIR%\storage\keys\public.key"

echo [4/5] Menjalankan ionCube Encoder pada netsight-core...
:: Pastikan 'ioncube_encoder.exe' sudah ditambahkan ke Environment Variables (PATH) Windows
:: Atau ubah 'ioncube_encoder' di bawah menjadi lokasi absolut (contoh: "C:\Program Files\ioncube\ioncube_encoder.exe")
ioncube_encoder "%BUILD_DIR%\packages\vandiza\netsight-core\src" -o "%BUILD_DIR%\packages\vandiza\netsight-core\src_encoded"

if exist "%BUILD_DIR%\packages\vandiza\netsight-core\src_encoded" (
    :: Jika enkripsi berhasil, hapus folder src mentah dan ganti namanya
    rmdir /s /q "%BUILD_DIR%\packages\vandiza\netsight-core\src"
    ren "%BUILD_DIR%\packages\vandiza\netsight-core\src_encoded" src
    echo   - [SUCCESS] Enkripsi berhasil dilakukan!
) else (
    echo   - [WARNING] ionCube Encoder gagal berjalan atau belum terinstal. 
    echo   - Folder core dibiarkan tanpa enkripsi (Plain Text).
)

echo [5/5] Mengompresi hasil build ke dalam ZIP...
powershell -Command "Compress-Archive -Path '%BUILD_DIR%\*' -DestinationPath '%ZIP_FILE%' -Force"

echo ===================================================
echo BUILD SELESAI!
echo File siap dikirim ke klien: %ZIP_FILE%
echo ===================================================
pause
