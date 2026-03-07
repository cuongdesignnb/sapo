<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Services\AttendanceDeviceDrivers\RonaldJackZkLibDriver;
use Illuminate\Http\Request;

class AttendanceDeviceController extends Controller
{
    public function index(Request $request)
    {
        $query = AttendanceDevice::query()->with(['warehouse:id,name']);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $devices = $query->orderByDesc('id')->get();

        return response()->json([
            'success' => true,
            'data' => $devices,
        ]);
    }

    public function show(AttendanceDevice $device)
    {
        $device->load(['warehouse:id,name']);

        return response()->json([
            'success' => true,
            'data' => $device,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'warehouse_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['required', 'ip'],
            'tcp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'comm_key' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'status' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        $device = AttendanceDevice::create([
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'name' => $data['name'],
            'model' => $data['model'] ?? null,
            'serial_number' => $data['serial_number'] ?? null,
            'ip_address' => $data['ip_address'],
            'tcp_port' => $data['tcp_port'] ?? 4370,
            'comm_key' => $data['comm_key'] ?? 0,
            'status' => $data['status'] ?? 'active',
            'notes' => $data['notes'] ?? null,
        ]);

        $device->load(['warehouse:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Tạo máy chấm công thành công',
            'data' => $device,
        ]);
    }

    public function update(Request $request, AttendanceDevice $device)
    {
        $data = $request->validate([
            'warehouse_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['required', 'ip'],
            'tcp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'comm_key' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'status' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        $device->update([
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'name' => $data['name'],
            'model' => $data['model'] ?? null,
            'serial_number' => $data['serial_number'] ?? null,
            'ip_address' => $data['ip_address'],
            'tcp_port' => $data['tcp_port'] ?? 4370,
            'comm_key' => $data['comm_key'] ?? 0,
            'status' => $data['status'] ?? $device->status,
            'notes' => $data['notes'] ?? null,
        ]);

        $device->load(['warehouse:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật máy chấm công thành công',
            'data' => $device,
        ]);
    }

    public function destroy(AttendanceDevice $device)
    {
        $device->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa máy chấm công thành công',
        ]);
    }

    public function testConnection(AttendanceDevice $device)
    {
        $timeoutSeconds = 3;

        $tcp = $this->probeTcp($device->ip_address, (int) $device->tcp_port, $timeoutSeconds);
        if ($tcp['ok']) {
            return response()->json([
                'success' => true,
                'message' => 'Kết nối TCP thành công',
                'data' => [
                    'protocol' => 'tcp',
                    'ip' => $device->ip_address,
                    'port' => (int) $device->tcp_port,
                ],
            ]);
        }

        // Many attendance devices (e.g. ZKTeco) use UDP port 4370.
        $udp = $this->probeUdp($device->ip_address, (int) $device->tcp_port, $timeoutSeconds);
        if ($udp['ok']) {
            return response()->json([
                'success' => true,
                'message' => 'TCP thất bại nhưng UDP có thể truy cập (thiết bị có thể dùng UDP).',
                'data' => [
                    'protocol' => 'udp',
                    'ip' => $device->ip_address,
                    'port' => (int) $device->tcp_port,
                    'note' => 'UDP không có bắt tay như TCP; đây là kiểm tra cơ bản. Đồng bộ log cần driver/SDK theo model.',
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Không kết nối được (TCP/UDP). Vui lòng kiểm tra IP/Port/Firewall/mạng LAN.',
            'error' => [
                'tcp' => $tcp['error'] ?? null,
                'udp' => $udp['error'] ?? null,
            ],
        ], 422);
    }

    public function sync(AttendanceDevice $device)
    {
        $timeoutSeconds = 5;

        $tcp = $this->probeTcp($device->ip_address, (int) $device->tcp_port, $timeoutSeconds);
        $udp = ['ok' => false];
        if (!$tcp['ok']) {
            $udp = $this->probeUdp($device->ip_address, (int) $device->tcp_port, $timeoutSeconds);
        }

        if (!$tcp['ok'] && !$udp['ok']) {
            return response()->json([
                'success' => false,
                'message' => 'Không kết nối được để đồng bộ. Vui lòng kiểm tra IP/Port/Firewall/mạng LAN.',
                'error' => [
                    'tcp' => $tcp['error'] ?? null,
                    'udp' => $udp['error'] ?? null,
                ],
            ], 422);
        }

        try {
            $driver = new RonaldJackZkLibDriver();
            $result = $driver->sync($device, $timeoutSeconds);

            return response()->json([
                'success' => true,
                'message' => 'Đã đồng bộ log từ thiết bị Ronald Jack (ZK UDP 4370).',
                'data' => [
                    'device' => $device->fresh(),
                    'sync' => $result,
                    'protocol' => $tcp['ok'] ? 'tcp' : 'udp',
                ],
            ]);
        } catch (\Throwable $e) {
            $rawMessage = (string) $e->getMessage();
            $hint = null;
            $lower = strtolower($rawMessage);
            if (str_contains($lower, 'timed out') || str_contains($lower, 'resource temporarily unavailable')) {
                $hint = 'Timeout khi đọc UDP: kiểm tra server có ra được UDP 4370 tới máy chấm công (firewall/NAT/LAN).';
            } elseif (str_contains($lower, 'unavailable') || str_contains($lower, 'network')) {
                $hint = 'Lỗi mạng khi đọc dữ liệu từ thiết bị: kiểm tra firewall và đường truyền LAN.';
            } elseif (str_contains($lower, 'unauth') || str_contains($lower, 'unaut')) {
                $hint = 'Thiết bị trả về UNAUTH: thường do Comm Key/Communication Password trên máy khác 0 hoặc không tương thích.';
            } elseif (str_contains($lower, 'comm key') || str_contains($lower, 'password')) {
                $hint = 'Kiểm tra Comm Key/Communication Password trên máy: nên để 0 nếu dùng driver này.';
            }

            return response()->json([
                'success' => false,
                'message' => trim('Kết nối được nhưng không đọc được log từ thiết bị. ' . ($hint ? $hint . ' ' : '') . ($rawMessage ? 'Chi tiết: ' . $rawMessage : '')),
                'data' => [
                    'protocol' => $tcp['ok'] ? 'tcp' : 'udp',
                    'comm_key' => (int) $device->comm_key,
                    'exception' => [
                        'class' => get_class($e),
                        'message' => $rawMessage,
                    ],
                    'hint' => $hint,
                ],
            ], 422);
        }
    }

    private function probeTcp(string $ip, int $port, int $timeoutSeconds): array
    {
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($ip, $port, $errno, $errstr, $timeoutSeconds);

        if (!$socket) {
            return [
                'ok' => false,
                'error' => [
                    'code' => $errno,
                    'detail' => $errstr,
                ],
            ];
        }

        fclose($socket);
        return ['ok' => true];
    }

    private function probeUdp(string $ip, int $port, int $timeoutSeconds): array
    {
        $errno = 0;
        $errstr = '';
        $sock = @stream_socket_client(
            sprintf('udp://%s:%d', $ip, $port),
            $errno,
            $errstr,
            $timeoutSeconds,
            STREAM_CLIENT_CONNECT
        );

        if (!$sock) {
            return [
                'ok' => false,
                'error' => [
                    'code' => $errno,
                    'detail' => $errstr,
                ],
            ];
        }

        stream_set_timeout($sock, $timeoutSeconds);
        // Send a single byte as a basic reachability probe.
        @fwrite($sock, "\0");
        fclose($sock);
        return ['ok' => true];
    }
}
