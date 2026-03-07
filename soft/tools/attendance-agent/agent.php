<?php

// Load Windows-patched ZKLib before Composer autoload to override vendor class.
$override = __DIR__ . '/src/ZKLib/ZKLib.php';
if (is_file($override)) {
    require_once $override;
}

require __DIR__ . '/vendor/autoload.php';

use ZKLib\ZKLib;

function arg(string $name, ?string $default = null): ?string {
    foreach ($GLOBALS['argv'] as $index => $value) {
        if ($value === "--{$name}" && isset($GLOBALS['argv'][$index + 1])) {
            return $GLOBALS['argv'][$index + 1];
        }
        if (str_starts_with($value, "--{$name}=")) {
            return substr($value, strlen($name) + 3);
        }
    }
    return $default;
}

$server = rtrim((string) arg('server', ''), '/');
$deviceId = (int) arg('device-id', '0');
$deviceIp = (string) arg('device-ip', '');
$port = (int) arg('port', '4370');
$secret = (string) arg('secret', '');
$timeout = (int) arg('timeout', '5');
$insecure = (string) arg('insecure', '0');
$caBundle = (string) arg('ca-bundle', '');
$httpTimeout = (int) arg('http-timeout', '20');
$connectTimeout = (int) arg('connect-timeout', '10');
$ipResolve = strtolower((string) arg('ip-resolve', 'v4'));

if ($server === '' || $deviceId <= 0 || $deviceIp === '' || $secret === '') {
    fwrite(STDERR, "Usage: php agent.php --server=https://app.domain --device-id=1 --device-ip=192.168.1.222 --port=4370 --secret=...\n");
    exit(2);
}

if (!extension_loaded('sockets')) {
    fwrite(STDERR, "PHP extension sockets is required\n");
    exit(2);
}
if (!function_exists('curl_init')) {
    fwrite(STDERR, "PHP extension curl is required\n");
    exit(2);
}

$stateFile = __DIR__ . '/state.json';
$state = ['last_punched_at' => null];
if (is_file($stateFile)) {
    $raw = json_decode((string) file_get_contents($stateFile), true);
    if (is_array($raw)) $state = array_merge($state, $raw);
}

$since = null;
if (!empty($state['last_punched_at'])) {
    $since = new DateTimeImmutable($state['last_punched_at']);
    $since = $since->sub(new DateInterval('PT5M')); // safety window
}

// Workaround: upstream library throws ZKLib\RuntimeException (may not exist)
if (!class_exists('ZKLib\\RuntimeException', false)) {
    class_alias(RuntimeException::class, 'ZKLib\\RuntimeException');
}

$zk = new ZKLib($deviceIp, $port);
$zk->setTimeout(['sec' => $timeout, 'usec' => 0]);
$zk->connect();

try {
    try { $zk->disable(); } catch (Throwable $e) {}
    $attendances = $zk->getAttendance();
} finally {
    try { $zk->enable(); } catch (Throwable $e) {}
    $zk->disconnect();
}

// Collect all logs first
$allLogs = [];
$maxPunchedAt = null;

foreach ($attendances as $a) {
    $dt = DateTimeImmutable::createFromMutable($a->getDateTime());
    if ($since && $dt <= $since) continue;

    $iso = $dt->format(DateTimeInterface::ATOM);
    $allLogs[] = [
        'device_user_id' => (string) $a->getUserId(),
        'punched_at' => $iso,
        'event_type' => $a->isOut() ? 'out' : 'in',
        'raw' => [
            'type' => $a->getType(),
            'status' => $a->getStatus(),
            'validated_by' => $a->validatedBy(),
        ],
    ];

    if ($maxPunchedAt === null || $dt > $maxPunchedAt) {
        $maxPunchedAt = $dt;
    }
}

echo "Total logs to sync: " . count($allLogs) . "\n";

if (count($allLogs) === 0) {
    echo "No new logs to sync.\n";
    exit(0);
}

// Send in batches of 500 records
$batchSize = 500;
$batches = array_chunk($allLogs, $batchSize);
$totalBatches = count($batches);
$successCount = 0;
$failCount = 0;

echo "Sending in $totalBatches batch(es) of max $batchSize records each...\n\n";

foreach ($batches as $batchIndex => $batchLogs) {
    $batchNum = $batchIndex + 1;
    echo "--- Batch $batchNum/$totalBatches (" . count($batchLogs) . " records) ---\n";
    
    $payload = json_encode([
        'attendance_device_id' => $deviceId,
        'logs' => $batchLogs,
    ], JSON_UNESCAPED_UNICODE);

    $ts = (string) time();
    $sig = hash_hmac('sha256', $ts . '.' . $payload, $secret);

    $ch = curl_init();
    $fullUrl = $server . '/api/attendance-agent/push-logs';
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $fullUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => $connectTimeout ?: 10,
        CURLOPT_IPRESOLVE => ($ipResolve === 'v6') ? CURL_IPRESOLVE_V6 : CURL_IPRESOLVE_V4,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Attendance-Agent-Timestamp: ' . $ts,
            'X-Attendance-Agent-Signature: ' . $sig,
        ],
        CURLOPT_TIMEOUT => $httpTimeout ?: 30,
    ]);

    $respBody = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    echo "Payload: " . strlen($payload) . " bytes, HTTP: $httpCode\n";
    
    if ($curlErr) {
        echo "cURL Error: $curlErr\n";
        $failCount++;
        continue;
    }
    
    if ($httpCode >= 200 && $httpCode < 300) {
        $successCount++;
        echo "OK\n";
    } else {
        $failCount++;
        echo "FAILED: $respBody\n";
    }
    
    // Small delay between batches
    if ($batchNum < $totalBatches) {
        usleep(200000); // 200ms
    }
}

echo "\n=== Summary ===\n";
echo "Success: $successCount/$totalBatches batches\n";
echo "Failed: $failCount/$totalBatches batches\n";

if ($failCount === 0 && $maxPunchedAt) {
    $state['last_punched_at'] = $maxPunchedAt->format(DateTimeInterface::ATOM);
    file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "State saved.\n";
    exit(0);
}

exit($failCount > 0 ? 1 : 0);
