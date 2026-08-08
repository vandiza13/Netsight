<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Vandiza\NetsightCore\Models\AcsDevice;

class CheckAcsTasks extends Command
{
    protected $signature = 'acs:check-tasks {genieacs_id?}';
    protected $description = 'Check queued tasks and faults for a device in GenieACS';

    public function handle()
    {
        $genieacsId = $this->argument('genieacs_id');

        if (!$genieacsId) {
            $devices = AcsDevice::orderBy('updated_at', 'desc')->take(20)->get();
            if ($devices->isEmpty()) {
                $this->error('Tidak ada device di database lokal.');
                return 1;
            }
            
            $options = [];
            foreach ($devices as $d) {
                $options[$d->genieacs_id] = "{$d->genieacs_id} (SN: {$d->serial_number})";
            }
            
            $choice = $this->choice(
                'Pilih ID Modem (menampilkan 20 terakhir):',
                array_values($options)
            );
            
            // Extract the genieacs_id from the chosen string
            $genieacsId = array_search($choice, $options);
        }

        $encodedId = rawurlencode($genieacsId);
        $genieUrl = rtrim(env('GENIEACS_NBI_URL', 'http://genieacs:7557'), '/');

        $this->info("Fetching tasks for: $genieacsId");
        
        $response = Http::timeout(10)->get("$genieUrl/devices/$encodedId/tasks");

        if ($response->failed()) {
            $this->error("Failed to connect to GenieACS.");
            $this->error($response->body());
            return 1;
        }

        $tasks = $response->json();
        if (empty($tasks)) {
            $this->warn("No queued tasks found for this device.");
            return 0;
        }

        $hasFaults = false;
        foreach ($tasks as $i => $task) {
            $this->info("Task #" . ($i+1) . " [" . ($task['name'] ?? 'Unknown') . "]");
            if (isset($task['parameterValues'])) {
                foreach ($task['parameterValues'] as $param) {
                    $val = is_bool($param[1]) ? ($param[1] ? 'true' : 'false') : $param[1];
                    $type = $param[2] ?? 'no-type';
                    $this->line("  - {$param[0]} = {$val} ({$type})");
                }
            }
            
            if (isset($task['fault'])) {
                $hasFaults = true;
                $this->error("🚨 FAULT DETECTED 🚨");
                $this->line("Fault Code   : " . ($task['fault']['faultCode'] ?? 'Unknown'));
                $this->line("Fault String : " . ($task['fault']['faultString'] ?? 'Unknown'));
                $this->line("Detail       : " . json_encode($task['fault']['setParameterValuesFault'] ?? [], JSON_PRETTY_PRINT));
            } else {
                $this->info("Status: Queued / Processing");
            }
            $this->line("--------------------------------------------------------");
        }

        if ($hasFaults) {
            $this->error("Modem menolak beberapa perintah. Silakan periksa pesan Fault di atas.");
        } else {
            $this->info("Semua task masih berada di antrean atau sedang diproses.");
        }

        return 0;
    }
}
