<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyAttendanceAgentSignature
{
    public function handle(Request $request, Closure $next)
    {
        $key = config('services.attendance_agent.hmac_key', 'your-long-random-secret-here-change-me');
        $tolerance = (int) config('services.attendance_agent.timestamp_tolerance', 300);
        $deviceId = $request->header('X-Device-Id', '');
        $timestamp = $request->header('X-Timestamp', '');
        $signature = $request->header('X-Signature', '');

        if (!$key || !$deviceId || !$timestamp || !$signature) {
            return response()->json(['error' => 'Missing auth headers'], 401);
        }

        // Chống replay attack — kiểm tra timestamp lệch
        if (abs(time() - (int) $timestamp) > $tolerance) {
            return response()->json(['error' => 'Timestamp expired'], 401);
        }

        // Lấy raw body
        $rawBody = file_get_contents('php://input');
        if (empty($rawBody)) {
            $rawBody = $request->getContent();
        }

        // Tính chữ ký mong đợi
        $payload = "{$timestamp}.{$deviceId}.{$rawBody}";
        $expected = hash_hmac('sha256', $payload, $key);

        if (!hash_equals($expected, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Lưu device_id vào request để controller dùng
        $request->attributes->set('attendance_device_id', $deviceId);

        return $next($request);
    }
}
