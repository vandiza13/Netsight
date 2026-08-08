<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DebugAcsDevice extends Command
{
    protected $signature = 'acs:debug-ip {genieacs_id}';
    protected $description = 'Debug ConnectionRequestURL and PeriodicInformInterval of a device in GenieACS';

    public function handle()
    {
        $genieacsId = $this->argument('genieacs_id');
        $encodedId = rawurlencode($genieacsId);
        $genieUrl = rtrim(env('GENIEACS_NBI_URL', 'http://genieacs:7557'), '/');

        $this->info("Fetching device data for: $genieacsId");
        
        $response = Http::timeout(10)->get("$genieUrl/devices", [
            'query' => json_encode(['_id' => $genieacsId])
        ]);

        if ($response->failed() || empty($response->json())) {
            $this->error("Failed to connect to GenieACS or device not found.");
            return 1;
        }

        $devices = $response->json();
        $device = $devices[0];
        
        $this->info("\n=== DIAGNOSTIK MODEM ===");
        $this->line("IP Address (dari GenieACS): " . ($device['_ip'] ?? 'Tidak ada'));
        $this->line("Terakhir Lapor (Last Inform): " . ($device['_lastInform'] ?? 'Tidak ada'));
        
        // Cari ConnectionRequestURL
        $crUrl = $device['InternetGatewayDevice']['ManagementServer']['ConnectionRequestURL']['_value'] 
                 ?? $device['Device']['ManagementServer']['ConnectionRequestURL']['_value'] 
                 ?? 'Tidak diketahui';
        $this->info("\n[1] Jalur Connection Request (Arah Turun)");
        $this->line("URL: " . $crUrl);
        
        // Cari PeriodicInformInterval
        $pii = $device['InternetGatewayDevice']['ManagementServer']['PeriodicInformInterval']['_value'] 
               ?? $device['Device']['ManagementServer']['PeriodicInformInterval']['_value'] 
               ?? 'Tidak diketahui';
        $this->info("\n[2] Siklus Lapor Otomatis (Periodic Inform)");
        $this->line("Interval Bawaan Pabrik: " . $pii . " detik (" . (is_numeric($pii) ? ($pii/60)." menit)" : ")"));

        $this->info("\n=========================\n");

        if (is_numeric($pii) && $pii > 300) {
            $this->warn("Ternyata interval lapor otomatis modem ini sangat lama (" . ($pii/60) . " menit)!");
            $this->warn("Oleh karena itu antrean nyangkut lebih dari 3 menit karena modem belum waktunya lapor.");
        }
        
        return 0;
    }
}
