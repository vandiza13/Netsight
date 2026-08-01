<?php
/**
 * SNMP Diagnostic Tool v2 - OLT 1 (HA7304)
 * 
 * Jalankan: php test_olt1_diag.php
 */

$host = '10.99.99.2:1161';
$community = 'public';

// New Firmware (25355) OIDs
$statusOid = '1.3.6.1.4.1.25355.3.2.6.3.2.1.39';
$rxPowerOid = '1.3.6.1.4.1.25355.3.2.6.14.2.1.8';
$macOid = '1.3.6.1.4.1.25355.3.2.6.3.2.1.11';

echo "=============================================================\n";
echo "  SNMP Diagnostic v2 - OLT 1 (HA7304) @ {$host}\n";
echo "  PHP " . phpversion() . "\n";
echo "=============================================================\n\n";

snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);

// --- Test 1: Full walk Status OID ---
echo "--- TEST 1: Full walk Status OID ---\n";
$statusResult = @snmp2_real_walk($host, $community, $statusOid, 15000000, 3);
if ($statusResult === false) {
    echo "  FAILED\n";
} else {
    echo "  Total: " . count($statusResult) . " entries\n";
    
    // Count per PON
    $ponCounts = [];
    foreach ($statusResult as $oid => $val) {
        $suffix = str_replace(".{$statusOid}.", '', $oid);
        $suffix = ltrim($suffix, '.');
        $parts = explode('.', $suffix);
        if (count($parts) >= 3) {
            $ponKey = "{$parts[0]}.{$parts[1]}"; // board.pon
            $ponCounts[$ponKey] = ($ponCounts[$ponKey] ?? 0) + 1;
        } elseif (count($parts) >= 2) {
            $ponKey = "{$parts[0]}";
            $ponCounts[$ponKey] = ($ponCounts[$ponKey] ?? 0) + 1;
        }
    }
    echo "  Distribution per PON:\n";
    ksort($ponCounts);
    foreach ($ponCounts as $pon => $cnt) {
        echo "    PON {$pon}: {$cnt} ONUs\n";
    }
}
echo "\n";

// --- Test 2: Per-PON walk with CORRECT 2-level prefix ---
echo "--- TEST 2: Per-PON walk (CORRECT prefix: board.pon) ---\n";
$correctPrefixes = ['1.1', '1.2', '1.3', '1.4'];
$totalPerPon = 0;

foreach ($correctPrefixes as $prefix) {
    $chunkOid = "{$statusOid}.{$prefix}";
    $result = @snmp2_real_walk($host, $community, $chunkOid, 15000000, 3);
    if ($result === false) {
        echo "  PON {$prefix}: FAILED\n";
    } else {
        $cnt = count($result);
        $totalPerPon += $cnt;
        echo "  PON {$prefix}: {$cnt} ONUs\n";
    }
    usleep(500000);
}
echo "  TOTAL: {$totalPerPon}\n\n";

// --- Test 3: Walk the PARENT table to see all available columns ---
echo "--- TEST 3: Walk parent table 1.3.6.1.4.1.25355.3.2.6.3.2.1 (first 500 entries) ---\n";
$parentOid = '1.3.6.1.4.1.25355.3.2.6.3.2.1';
$parentResult = @snmp2_real_walk($host, $community, $parentOid, 30000000, 2);
if ($parentResult === false) {
    echo "  FAILED\n";
} else {
    echo "  Total entries in parent table: " . count($parentResult) . "\n";
    
    // Extract unique column numbers
    $columns = [];
    foreach ($parentResult as $oid => $val) {
        $relative = str_replace(".{$parentOid}.", '', $oid);
        $relative = ltrim($relative, '.');
        $parts = explode('.', $relative);
        if (!empty($parts[0])) {
            $col = $parts[0];
            $columns[$col] = ($columns[$col] ?? 0) + 1;
        }
    }
    ksort($columns, SORT_NUMERIC);
    echo "  Columns found (column_number => entry_count):\n";
    foreach ($columns as $col => $cnt) {
        $label = match($col) {
            '1' => 'onu-id?',
            '11' => 'mac_addr',
            '25' => 'distance',
            '37' => 'description',
            '39' => 'status',
            default => '',
        };
        echo "    Column {$col}: {$cnt} entries {$label}\n";
    }
}
echo "\n";

// --- Test 4: Try alternative status OIDs that some C-Data firmware versions use ---
echo "--- TEST 4: Alternative Status OIDs ---\n";
$altOids = [
    'Col 1 (onu-index)'  => '1.3.6.1.4.1.25355.3.2.6.3.2.1.1',
    'Col 2'              => '1.3.6.1.4.1.25355.3.2.6.3.2.1.2',
    'Col 3'              => '1.3.6.1.4.1.25355.3.2.6.3.2.1.3',
    'Col 4'              => '1.3.6.1.4.1.25355.3.2.6.3.2.1.4',
    'Col 5'              => '1.3.6.1.4.1.25355.3.2.6.3.2.1.5',
    'Table 2 status'     => '1.3.6.1.4.1.25355.3.2.6.3.3.1.39',
    'Table 3 status'     => '1.3.6.1.4.1.25355.3.2.6.3.1.1.39',
    'Olt-pon-onu table'  => '1.3.6.1.4.1.25355.3.2.6.4.2.1.1',
    'Olt-pon table'      => '1.3.6.1.4.1.25355.3.2.6.4.1.1.1',
];

foreach ($altOids as $name => $oid) {
    $result = @snmp2_real_walk($host, $community, $oid, 10000000, 2);
    if ($result === false) {
        echo "  {$name} ({$oid}): FAILED\n";
    } else {
        $cnt = count($result);
        echo "  {$name} ({$oid}): {$cnt} entries\n";
        if ($cnt > 0 && $cnt <= 5) {
            foreach ($result as $k => $v) {
                echo "    {$k} => {$v}\n";
            }
        } elseif ($cnt > 5) {
            $keys = array_keys($result);
            echo "    First: {$keys[0]} => {$result[$keys[0]]}\n";
            echo "    Last:  {$keys[$cnt-1]} => {$result[$keys[$cnt-1]]}\n";
        }
    }
    usleep(300000);
}
echo "\n";

// --- Test 5: Walk Rx Power table to see if it also returns only 93 ---
echo "--- TEST 5: Full walk Rx Power OID ---\n";
$rxResult = @snmp2_real_walk($host, $community, $rxPowerOid, 15000000, 3);
if ($rxResult === false) {
    echo "  FAILED\n";
} else {
    echo "  Total: " . count($rxResult) . " entries\n";
    // Distribution per PON
    $ponCounts = [];
    foreach ($rxResult as $oid => $val) {
        $suffix = str_replace(".{$rxPowerOid}.", '', $oid);
        $suffix = ltrim($suffix, '.');
        $parts = explode('.', $suffix);
        if (count($parts) >= 3) {
            $ponKey = "{$parts[0]}.{$parts[1]}";
            $ponCounts[$ponKey] = ($ponCounts[$ponKey] ?? 0) + 1;
        }
    }
    echo "  Distribution per PON:\n";
    ksort($ponCounts);
    foreach ($ponCounts as $pon => $cnt) {
        echo "    PON {$pon}: {$cnt} entries\n";
    }
}
echo "\n";

// --- Test 6: Walk MAC Address to see distribution ---
echo "--- TEST 6: Full walk MAC OID ---\n";
$macResult = @snmp2_real_walk($host, $community, $macOid, 15000000, 3);
if ($macResult === false) {
    echo "  FAILED\n";
} else {
    echo "  Total: " . count($macResult) . " entries\n";
    $ponCounts = [];
    foreach ($macResult as $oid => $val) {
        $suffix = str_replace(".{$macOid}.", '', $oid);
        $suffix = ltrim($suffix, '.');
        $parts = explode('.', $suffix);
        if (count($parts) >= 3) {
            $ponKey = "{$parts[0]}.{$parts[1]}";
            $ponCounts[$ponKey] = ($ponCounts[$ponKey] ?? 0) + 1;
        }
    }
    echo "  Distribution per PON:\n";
    ksort($ponCounts);
    foreach ($ponCounts as $pon => $cnt) {
        echo "    PON {$pon}: {$cnt} entries\n";
    }
}
echo "\n";

// --- Test 7: Broadest possible walk on 25355 enterprise tree ---
echo "--- TEST 7: Walk top-level 25355.3.2.6 subtrees ---\n";
$topOids = [
    '25355.3.2.6.1' => 'System/Global',
    '25355.3.2.6.2' => 'PON Interface',
    '25355.3.2.6.3' => 'ONU Info (current)',
    '25355.3.2.6.4' => 'ONU Config',
    '25355.3.2.6.5' => 'ONU Stats',
    '25355.3.2.6.6' => 'VLAN',
    '25355.3.2.6.7' => 'Bandwidth',
    '25355.3.2.6.8' => 'Multicast',
    '25355.3.2.6.9' => 'ACL',
    '25355.3.2.6.10' => 'QoS',
    '25355.3.2.6.11' => 'STP',
    '25355.3.2.6.12' => 'IGMP',
    '25355.3.2.6.13' => 'Port',
    '25355.3.2.6.14' => 'Optical (Rx/Tx Power)',
    '25355.3.2.6.15' => 'Diagnostics',
];

foreach ($topOids as $suffix => $label) {
    $oid = "1.3.6.1.4.1.{$suffix}";
    $result = @snmp2_real_walk($host, $community, $oid, 10000000, 1);
    if ($result === false) {
        echo "  {$label} (.{$suffix}): FAILED/EMPTY\n";
    } else {
        echo "  {$label} (.{$suffix}): " . count($result) . " entries\n";
    }
    usleep(300000);
}
echo "\n";

echo "=============================================================\n";
echo "  DIAGNOSIS COMPLETE\n";
echo "=============================================================\n";
