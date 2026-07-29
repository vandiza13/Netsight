<?php
require 'vendor/autoload.php';

$activeSessions = [
    ['name' => 'hadi'],
    ['name' => 'joko']
];

$secrets = [
    ['name' => 'hadi', 'profile' => 'GOLD', 'rate-limit' => '10M/10M']
];

$queues = [
    ['name' => '<pppoe-joko>', 'max-limit' => '5M/5M']
];

$queueLimits = [];
foreach ($queues as $queue) {
    if (isset($queue['name']) && !empty($queue['max-limit'])) {
        $name = $queue['name'];
        $cleanName = preg_replace('/^<pppoe-(.*)>$/', '$1', $name);
        $queueLimits[$cleanName] = $queue['max-limit'];
        $queueLimits[$name] = $queue['max-limit'];
    }
}

$activeUsersMap = [];
foreach ($activeSessions as $session) {
    if (isset($session['name'])) {
        $activeUsersMap[$session['name']] = true;
    }
}

$syncedCount = 0;
$upsertData = [];
$now = "2024-01-01 12:00:00";
$processedUsernames = [];

foreach ($secrets as $secret) {
    $username = $secret['name'] ?? null;
    if (! $username) continue;
    
    $processedUsernames[$username] = true;
    $profileName = $secret['profile'] ?? null;
    $rawRateLimit = !empty($secret['rate-limit']) ? $secret['rate-limit'] : '';
    $isSecretActive = ! isset($secret['disabled']) || $secret['disabled'] === 'false';
    $isOnline = isset($activeUsersMap[$username]);

    $upsertData[] = [
        'router_id' => 1,
        'username' => $username,
        'profile' => $profileName,
        'package_limit_mbps' => $rawRateLimit,
        'is_active_last_check' => $isSecretActive || $isOnline,
        'synced_at' => $now,
    ];
    $syncedCount++;
}

foreach ($activeSessions as $session) {
    $username = $session['name'] ?? null;
    if (! $username || isset($processedUsernames[$username])) continue;
    
    $rawRateLimit = $queueLimits[$username] ?? '';
    
    $upsertData[] = [
        'router_id' => 1,
        'username' => $username,
        'profile' => 'RADIUS',
        'package_limit_mbps' => $rawRateLimit,
        'is_active_last_check' => true,
        'synced_at' => $now,
    ];
    $syncedCount++;
}

print_r($upsertData);
