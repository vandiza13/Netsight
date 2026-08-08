<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DeleteStunPreset extends Command
{
    protected $signature = 'acs:delete-stun';
    protected $description = 'Delete the poisonous STUN preset from GenieACS';

    public function handle()
    {
        $genieUrl = rtrim(env('GENIEACS_NBI_URL', 'http://genieacs:7557'), '/');
        $presets = ["00_SET_FAST_INFORM", "00_SET_FAST_INFORM_AND_STUN"];

        foreach ($presets as $presetId) {
            $this->info("Menghapus preset beracun: $presetId");
            $response = Http::timeout(10)->delete("$genieUrl/presets/$presetId");

            if ($response->successful()) {
                $this->info("✅ Preset $presetId berhasil dihapus dari database!");
            } else {
                $this->error("❌ Gagal menghapus preset $presetId! HTTP Status: " . $response->status());
                $this->error("Response: " . $response->body());
            }
        }
        
        return 0;
    }
}
