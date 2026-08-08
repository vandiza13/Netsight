<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Vandiza\NetsightCore\Services\GenieAcsService;

class SetupAcsPresets extends Command
{
    protected $signature = 'acs:setup-presets';
    protected $description = 'Setup GenieACS presets (STUN preset disabled - uses $exists which kills GenieACS 1.2)';

    public function handle(GenieAcsService $acsService)
    {
        $this->warn("⚠️  Perintah ini sudah DINONAKTIFKAN secara permanen.");
        $this->warn("   Alasan: Preset 00_SET_FAST_INFORM_AND_STUN menggunakan operator \$exists");
        $this->warn("   yang TIDAK didukung GenieACS 1.2, menyebabkan SELURUH koneksi modem gagal.");
        $this->info("");
        $this->info("PeriodicInform sudah dikelola oleh preset 'netsight_auto' + provision 'netsight_refresh' (interval 300 detik).");
        return 0;
    }
}
