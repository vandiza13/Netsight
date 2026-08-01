<?php
/**
 * SNMP Diagnostic Tool for OLT 1 (HA7304)
 * 
 * Jalankan di server dengan: php test_olt1_diag.php
 * 
 * Script ini akan menguji berbagai metode SNMP walk untuk menentukan
 * metode mana yang bisa menarik SEMUA data dari OLT tanpa terpotong.
 */

// === KONFIGURASI OLT 1 ===
$host = '10.99.99.2:1161';
$community = 'public';
$statusOid = '1.3.6.1.4.1.25355.3.2.6.3.2.1.39'; // New Firmware Status OID
$rxPowerOid = '1.3.6.1.4.1.25355.3.2.6.14.2.1.8'; // New Firmware Rx Power OID
$macOid = '1.3.6.1.4.1.25355.3.2.6.3.2.1.11';     // New Firmware MAC OID

echo "=============================================================\n";
echo "  SNMP Diagnostic Tool - OLT 1 (HA7304) @ {$host}\n";
echo "=============================================================\n\n";

// --- Test 1: Check PHP SNMP capabilities ---
echo "--- TEST 1: PHP SNMP Extension Check ---\n";
echo "  snmp2_real_walk() exists: " . (function_exists('snmp2_real_walk') ? 'YES' : 'NO') . "\n";
echo "  snmprealwalk() exists:    " . (function_exists('snmprealwalk') ? 'YES' : 'NO') . "\n";
echo "  SNMP class exists:        " . (class_exists('SNMP') ? 'YES' : 'NO') . "\n";
echo "  PHP Version:              " . phpversion() . "\n";
if (class_exists('SNMP')) {
    $testSession = new SNMP(SNMP::VERSION_2c, '127.0.0.1', 'public');
    echo "  SNMP::max_oids property:  " . (property_exists($testSession, 'max_oids') ? 'YES' : 'NO') . "\n";
    $testSession->close();
}
echo "\n";

// --- Test 2: snmp2_real_walk (the OLD method) ---
echo "--- TEST 2: snmp2_real_walk() [Old Method - GETBULK default] ---\n";
snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);

$start = microtime(true);
$oldResult = @snmp2_real_walk($host, $community, $statusOid, 15000000, 3);
$elapsed = round(microtime(true) - $start, 2);

if ($oldResult === false) {
    echo "  RESULT: FAILED (returned false)\n";
    echo "  Time: {$elapsed}s\n";
} else {
    $count = count($oldResult);
    echo "  RESULT: {$count} entries returned\n";
    echo "  Time: {$elapsed}s\n";
    // Show first and last 3 entries
    $keys = array_keys($oldResult);
    echo "  First 3:\n";
    for ($i = 0; $i < min(3, $count); $i++) {
        echo "    {$keys[$i]} => {$oldResult[$keys[$i]]}\n";
    }
    echo "  Last 3:\n";
    for ($i = max(0, $count - 3); $i < $count; $i++) {
        echo "    {$keys[$i]} => {$oldResult[$keys[$i]]}\n";
    }
}
echo "\n";

// --- Test 3: SNMP OOP class with max_oids=15 ---
if (class_exists('SNMP')) {
    echo "--- TEST 3: SNMP OOP class with max_oids=15 ---\n";
    $start = microtime(true);
    
    $session = new SNMP(SNMP::VERSION_2c, $host, $community, 15000000, 3);
    $session->valueretrieval = SNMP_VALUE_PLAIN;
    $session->oid_output_format = SNMP_OID_OUTPUT_NUMERIC;
    $session->max_oids = 15;
    $session->exceptions_enabled = 0;
    
    $oopResult = @$session->walk($statusOid, false, 15);
    $elapsed = round(microtime(true) - $start, 2);
    $session->close();
    
    if ($oopResult === false) {
        echo "  RESULT: FAILED (returned false)\n";
        echo "  Time: {$elapsed}s\n";
    } else {
        $count = count($oopResult);
        echo "  RESULT: {$count} entries returned\n";
        echo "  Time: {$elapsed}s\n";
        $keys = array_keys($oopResult);
        echo "  First 3:\n";
        for ($i = 0; $i < min(3, $count); $i++) {
            echo "    {$keys[$i]} => {$oopResult[$keys[$i]]}\n";
        }
        echo "  Last 3:\n";
        for ($i = max(0, $count - 3); $i < $count; $i++) {
            echo "    {$keys[$i]} => {$oopResult[$keys[$i]]}\n";
        }
    }
    echo "\n";

    // --- Test 4: SNMP OOP class with max_oids=5 (even smaller) ---
    echo "--- TEST 4: SNMP OOP class with max_oids=5 [Ultra slow but ultra safe] ---\n";
    $start = microtime(true);
    
    $session = new SNMP(SNMP::VERSION_2c, $host, $community, 15000000, 3);
    $session->valueretrieval = SNMP_VALUE_PLAIN;
    $session->oid_output_format = SNMP_OID_OUTPUT_NUMERIC;
    $session->max_oids = 5;
    $session->exceptions_enabled = 0;
    
    $smallResult = @$session->walk($statusOid, false, 5);
    $elapsed = round(microtime(true) - $start, 2);
    $session->close();
    
    if ($smallResult === false) {
        echo "  RESULT: FAILED (returned false)\n";
        echo "  Time: {$elapsed}s\n";
    } else {
        $count = count($smallResult);
        echo "  RESULT: {$count} entries returned\n";
        echo "  Time: {$elapsed}s\n";
    }
    echo "\n";
} else {
    echo "--- TEST 3 & 4: SKIPPED (SNMP class not available) ---\n\n";
}

// --- Test 5: Manual GETNEXT walk (guaranteed complete, v2c) ---
echo "--- TEST 5: Manual GETNEXT walk [Guaranteed complete] ---\n";
$start = microtime(true);
$manualResults = [];
$currentOid = $statusOid;
$baseOidDotted = '.' . ltrim($statusOid, '.');
$maxIterations = 500; // Safety limit
$iteration = 0;

while ($iteration < $maxIterations) {
    $iteration++;
    $nextVal = @snmp2_getnext($host, $community, $currentOid, 5000000, 2);
    
    if ($nextVal === false) {
        echo "  [GETNEXT stopped at iteration {$iteration}: returned false]\n";
        break;
    }
    
    // snmp2_getnext returns the value, but we need the OID too.
    // Unfortunately snmp2_getnext doesn't return the OID directly.
    // We need to use snmp2_get to verify, or use the SNMP class.
    // Let's use a different approach - use snmp2_real_walk on small sub-OIDs
    break; // Can't do manual getnext easily with procedural API
}

// Instead, try walking per-PON (chunked approach)
echo "  [Manual GETNEXT not feasible with procedural API, trying per-PON walk...]\n\n";

// --- Test 6: Per-PON chunked walk ---
echo "--- TEST 6: Per-PON Chunked Walk (Status OID) ---\n";
$totalChunked = 0;
$ponPrefixes = ['1.1.1', '1.1.2', '1.1.3', '1.1.4']; // HA7304 has 4 PON ports

foreach ($ponPrefixes as $prefix) {
    $chunkOid = "{$statusOid}.{$prefix}";
    $start2 = microtime(true);
    $chunkResult = @snmp2_real_walk($host, $community, $chunkOid, 15000000, 3);
    $elapsed2 = round(microtime(true) - $start2, 2);
    
    if ($chunkResult === false) {
        echo "  PON {$prefix}: FAILED ({$elapsed2}s)\n";
    } else {
        $chunkCount = count($chunkResult);
        $totalChunked += $chunkCount;
        echo "  PON {$prefix}: {$chunkCount} entries ({$elapsed2}s)\n";
    }
    sleep(1); // Delay between chunks
}
echo "  TOTAL from chunked: {$totalChunked} entries\n\n";

// --- Test 7: Per-PON chunked walk for Rx Power ---
echo "--- TEST 7: Per-PON Chunked Walk (Rx Power OID) ---\n";
$totalRxChunked = 0;

foreach ($ponPrefixes as $prefix) {
    $chunkOid = "{$rxPowerOid}.{$prefix}";
    $start2 = microtime(true);
    $chunkResult = @snmp2_real_walk($host, $community, $chunkOid, 15000000, 3);
    $elapsed2 = round(microtime(true) - $start2, 2);
    
    if ($chunkResult === false) {
        echo "  PON {$prefix}: FAILED ({$elapsed2}s)\n";
    } else {
        $chunkCount = count($chunkResult);
        $totalRxChunked += $chunkCount;
        echo "  PON {$prefix}: {$chunkCount} entries ({$elapsed2}s)\n";
    }
    sleep(1);
}
echo "  TOTAL Rx Power from chunked: {$totalRxChunked} entries\n\n";

// --- Test 8: Per-PON chunked walk for MAC ---
echo "--- TEST 8: Per-PON Chunked Walk (MAC OID) ---\n";
$totalMacChunked = 0;

foreach ($ponPrefixes as $prefix) {
    $chunkOid = "{$macOid}.{$prefix}";
    $start2 = microtime(true);
    $chunkResult = @snmp2_real_walk($host, $community, $chunkOid, 15000000, 3);
    $elapsed2 = round(microtime(true) - $start2, 2);
    
    if ($chunkResult === false) {
        echo "  PON {$prefix}: FAILED ({$elapsed2}s)\n";
    } else {
        $chunkCount = count($chunkResult);
        $totalMacChunked += $chunkCount;
        echo "  PON {$prefix}: {$chunkCount} entries ({$elapsed2}s)\n";
    }
    sleep(1);
}
echo "  TOTAL MAC from chunked: {$totalMacChunked} entries\n\n";

echo "=============================================================\n";
echo "  DIAGNOSIS SUMMARY\n";
echo "=============================================================\n";
echo "  Expected ONUs (from OLT web UI): 158\n";
if ($oldResult !== false) {
    echo "  snmp2_real_walk (full):          " . count($oldResult) . "\n";
}
if (isset($oopResult) && $oopResult !== false) {
    echo "  SNMP OOP max_oids=15:            " . count($oopResult) . "\n";
}
if (isset($smallResult) && $smallResult !== false) {
    echo "  SNMP OOP max_oids=5:             " . count($smallResult) . "\n";
}
echo "  Per-PON chunked (Status):        {$totalChunked}\n";
echo "  Per-PON chunked (Rx Power):      {$totalRxChunked}\n";
echo "  Per-PON chunked (MAC):           {$totalMacChunked}\n";
echo "=============================================================\n";
