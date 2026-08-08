<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckNewDevices extends Command
{
    protected $signature = 'acs:check-new';
    protected $description = 'Check recently connected devices in GenieACS';

    public function handle()
    {
        $genieUrl = rtrim(env('GENIEACS_NBI_URL', 'http://genieacs:7557'), '/');
        
        $this->info("Mengambil 10 perangkat terakhir yang melapor ke GenieACS...");
        
        // Fetch all devices (GenieACS 1.2 requires query param or projection)
        $url = "$genieUrl/devices?query=%7B%7D";
        $response = Http::timeout(10)->get($url);

        if (!$response->successful()) {
            $this->error("Gagal terhubung ke GenieACS. Status: " . $response->status());
            $this->error("Response: " . $response->body());
            return 1;
        }

        $devices = $response->json();
        
        if (empty($devices)) {
            $this->warn("TIDAK ADA PERANGKAT SAMA SEKALI DI GENIEACS!");
            return 0;
        }

        usort($devices, function($a, $b) {
            $timeA = strtotime($a['_lastInform'] ?? '1970-01-01');
            $timeB = strtotime($b['_lastInform'] ?? '1970-01-01');
            return $timeB <=> $timeA;
        });

        $devices = array_slice($devices, 0, 10);

        foreach ($devices as $device) {
            $id = $device['_id'] ?? 'Unknown ID';
            $lastInform = $device['_lastInform'] ?? 'Belum pernah lapor';
            $ip = $device['InternetGatewayDevice']['WANDevice'][1]['WANConnectionDevice'][1]['WANPPPConnection'][1]['ExternalIPAddress']['_value'] ?? 
                  $device['InternetGatewayDevice']['WANDevice'][1]['WANConnectionDevice'][1]['WANIPConnection'][1]['ExternalIPAddress']['_value'] ?? 
                  'Tidak ada IP';
                  
            $this->info("ID: $id | Last Inform: $lastInform | IP: $ip");
        }
        
        return 0;
    }
}
