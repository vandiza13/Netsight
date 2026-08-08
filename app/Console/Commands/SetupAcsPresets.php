<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Vandiza\NetsightCore\Services\GenieAcsService;

class SetupAcsPresets extends Command
{
    protected $signature = 'acs:setup-presets';
    protected $description = 'Setup GenieACS Fast Inform and STUN Presets to solve NAT delays';

    public function handle(GenieAcsService $acsService)
    {
        $this->info("Membuat Preset STUN dan Fast Inform (60 detik) di GenieACS...");
        
        $success = $acsService->ensureFastInformPreset();
        
        if ($success) {
            $this->info("✅ Preset berhasil dibuat! Semua modem yang aktif akan diatur untuk lapor setiap 60 detik dan mengaktifkan STUN Server.");
            $this->info("Perubahan ini akan otomatis berlaku pada semua modem sesaat setelah mereka melapor untuk pertama kalinya.");
            return 0;
        } else {
            $this->error("❌ Gagal membuat preset di GenieACS. Cek error log.");
            return 1;
        }
    }
}
