<?php
/**
 * Test API với payload nhỏ - bypass thiết bị
 */

$serverUrl = 'https://app.cuongdesign.net';
$secret = getenv('AGENT_SECRET') ?: 'YOUR_SECRET_HERE'; // Thay bằng secret thật

$payload = json_encode([
    'attendance_device_id' => 1,
    'logs' => [
        [
            'device_user_id' => '1',
            'punched_at' => date('c'),
            'event_type' => 'in',
            'raw' => ['type' => 0, 'status' => 0, 'validated_by' => 'finger']
        ]
    ]
]);

echo "Payload size: " . strlen($payload) . " bytes\n";

$ts = (string) time();
$sig = hash_hmac('sha256', $ts . '.' . $payload, $secret);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $serverUrl . '/api/attendance-agent/push-logs',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Attendance-Agent-Timestamp: ' . $ts,
        'X-Attendance-Agent-Signature: ' . $sig,
    ],
]);

$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $code\n";
if ($err) echo "Error: $err\n";
echo "Response: $resp\n";
