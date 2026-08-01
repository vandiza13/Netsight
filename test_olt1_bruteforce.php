<?php
/**
 * SNMP Brute-Force GET Diagnostic - OLT 1 (HA7304)
 * 
 * Jalankan: php test_olt1_bruteforce.php
 */

$host = '10.99.99.2:1161';
$community = 'public';

// New Firmware (25355) OIDs
$statusOid = '1.3.6.1.4.1.25355.3.2.6.3.2.1.39';
$rxPowerOid = '1.3.6.1.4.1.25355.3.2.6.14.2.1.8';

echo "=============================================================\n";
echo "  SNMP BRUTE-FORCE GET - OLT 1 (HA7304) @ {$host}\n";
echo "=============================================================\n\n";

snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);

$foundStatus = [];
$foundRx = [];

echo "Menembak langsung 256 kemungkinan alamat ONU (PON 1-4, ONU 1-64)...\n";
echo "Mohon tunggu sekitar 5-10 detik...\n\n";

// Batch arrays for snmp2_get (PHP can accept an array of OIDs to GET at once)
// We will batch them in groups of 10 to avoid overwhelming the OLT
$batches = [];
$currentBatch = [];

for ($pon = 1; $pon <= 4; $pon++) {
    for ($onu = 1; $onu <= 64; $onu++) {
        $suffix = "1.{$pon}.{$onu}";
        $currentBatch[] = [
            'suffix' => $suffix,
            'status' => "{$statusOid}.{$suffix}",
            'rx' => "{$rxPowerOid}.{$suffix}"
        ];
        
        if (count($currentBatch) >= 15) {
            $batches[] = $currentBatch;
            $currentBatch = [];
        }
    }
}
if (!empty($currentBatch)) {
    $batches[] = $currentBatch;
}

$statusTotal = 0;
$rxTotal = 0;
$ponCounts = ['1' => 0, '2' => 0, '3' => 0, '4' => 0];

foreach ($batches as $index => $batch) {
    // Array of just the OIDs for this batch
    $statusOidsToGet = array_column($batch, 'status');
    $rxOidsToGet = array_column($batch, 'rx');
    
    // Get Status
    $statusResult = @snmp2_get($host, $community, $statusOidsToGet, 3000000, 1);
    if (is_array($statusResult)) {
        foreach ($statusResult as $oid => $val) {
            // If value is empty or contains 'No Such', it means the ONU doesn't exist
            if (trim($val) !== '' && stripos($val, 'No Such') === false) {
                $statusTotal++;
                // Extract PON from OID
                $parts = explode('.', $oid);
                $ponIndex = $parts[count($parts) - 2];
                if (isset($ponCounts[$ponIndex])) {
                    $ponCounts[$ponIndex]++;
                }
            }
        }
    }
    
    // Get Rx Power
    $rxResult = @snmp2_get($host, $community, $rxOidsToGet, 3000000, 1);
    if (is_array($rxResult)) {
        foreach ($rxResult as $oid => $val) {
            if (trim($val) !== '' && stripos($val, 'No Such') === false) {
                $rxTotal++;
            }
        }
    }
    
    usleep(50000); // 50ms delay between batches
}

echo "--- HASIL BRUTE-FORCE GET ---\n";
echo "Total Status ditemukan: {$statusTotal} ONU\n";
echo "Total Rx Power ditemukan: {$rxTotal} ONU\n\n";

echo "Distribusi per PON (berdasarkan Status):\n";
echo "PON 1: {$ponCounts['1']} ONU\n";
echo "PON 2: {$ponCounts['2']} ONU\n";
echo "PON 3: {$ponCounts['3']} ONU\n";
echo "PON 4: {$ponCounts['4']} ONU\n\n";

if ($statusTotal >= 150) {
    echo "✅ SUKSES! Brute-force menembus batasan Walk OLT!\n";
    echo "Ini berarti kita bisa mengubah logic backend untuk menembak langsung OID-nya alih-alih menggunakan Walk.\n";
} else {
    echo "❌ GAGAL. Meskipun ditembak langsung, OLT tetap menyembunyikan sisa ONU-nya (Total = {$statusTotal}).\n";
    echo "Ini menandakan bug parah di firmware OLT itu sendiri di mana MIB table tidak diupdate dengan data real.\n";
}

echo "=============================================================\n";
