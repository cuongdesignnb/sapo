<?php
/**
 * Minimal PHP cURL test - isolate the issue
 */

// Load config from environment or hardcode for test
$serverUrl = getenv('SERVER_URL') ?: 'https://app.cuongdesign.net';

echo "=== PHP cURL Test ===\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Server URL: $serverUrl\n\n";

// Test 1: Simple GET
echo "[TEST 1] GET /api/test\n";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $serverUrl . '/api/test',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_VERBOSE => true,
    CURLOPT_STDERR => fopen('php://stdout', 'w'),
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "\nHTTP Code: $httpCode\n";
if ($error) echo "Error: $error\n";
echo "Response: $response\n\n";

// Test 2: POST to push-logs
echo "[TEST 2] POST /api/attendance-agent/push-logs\n";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $serverUrl . '/api/attendance-agent/push-logs',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_VERBOSE => true,
    CURLOPT_STDERR => fopen('php://stdout', 'w'),
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => '{}',
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
    ],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "\nHTTP Code: $httpCode\n";
if ($error) echo "Error: $error\n";
echo "Response: $response\n\n";

echo "=== Done ===\n";
