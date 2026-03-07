<?php

namespace App\Services\AttendanceDeviceDrivers;

use App\Models\AttendanceDevice;
use App\Models\AttendanceLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use ZKLib\ZKLib;

class RonaldJackZkLibDriver
{
    /**
     * Sync attendance logs from a Ronald Jack device (usually ZKTeco-compatible UDP 4370).
     *
     * Notes:
     * - Requires PHP sockets extension.
     * - If device has a non-zero Comm Key / Communication Password, this driver may not authenticate.
     */
    public function sync(AttendanceDevice $device, int $timeoutSeconds = 5): array
    {
        if (!extension_loaded('sockets')) {
            throw new \RuntimeException('PHP extension "sockets" is required to sync from the device.');
        }

        // Workaround for upstream library bug: it throws an unqualified RuntimeException inside namespace ZKLib,
        // which resolves to ZKLib\RuntimeException (class may not exist on some deployments).
        if (!class_exists('ZKLib\\RuntimeException', false)) {
            class_alias(\RuntimeException::class, 'ZKLib\\RuntimeException');
        }

        $zk = new ZKLib($device->ip_address, (int) $device->tcp_port);
        $zk->setTimeout(['sec' => $timeoutSeconds, 'usec' => 0]);

        // connect() may return true even if device replies UNAUTH; probe a simple command to detect it.
        $zk->connect();

        $probeOk = false;
        try {
            $serial = $zk->getSerialNumber();
            $name = $serial ? null : $zk->getDeviceName();
            $version = ($serial || $name) ? null : $zk->getVersion();
            $probeOk = !empty($serial) || !empty($name) || !empty($version);
        } catch (\Throwable $e) {
            // Re-throw: controller will present a clean message.
            throw $e;
        }

        if (!$probeOk) {
            throw new \RuntimeException('Device responded but did not allow commands (possible UNAUTH/Comm Key or incompatible protocol).');
        }

        try {
            // Some devices behave better when temporarily disabled during reads.
            try {
                $zk->disable();
            } catch (\Throwable $e) {
                // ignore
            }

            $attendances = $zk->getAttendance();
        } finally {
            try {
                $zk->enable();
            } catch (\Throwable $e) {
                // ignore
            }
            $zk->disconnect();
        }

        $since = $device->last_sync_at ? Carbon::parse($device->last_sync_at)->subMinutes(5) : null;

        $rows = [];
        $deviceUserIds = [];
        foreach ($attendances as $attendance) {
            $deviceUserId = (string) $attendance->getUserId();
            $punchedAt = Carbon::instance($attendance->getDateTime());

            if ($since && $punchedAt->lessThanOrEqualTo($since)) {
                continue;
            }

            $deviceUserIds[] = $deviceUserId;

            $rows[] = [
                'attendance_device_id' => $device->id,
                'employee_id' => null, // fill after mapping
                'device_user_id' => $deviceUserId,
                'punched_at' => $punchedAt,
                'event_type' => $attendance->isOut() ? 'out' : 'in',
                'raw' => [
                    'type' => $attendance->getType(),
                    'status' => $attendance->getStatus(),
                    'validated_by' => $attendance->validatedBy(),
                ],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $deviceUserIds = array_values(array_unique($deviceUserIds));
        $employeeMap = [];
        if (!empty($deviceUserIds)) {
            $employeeMap = Employee::query()
                ->whereIn('attendance_code', $deviceUserIds)
                ->pluck('id', 'attendance_code')
                ->all();
        }

        foreach ($rows as &$row) {
            $row['employee_id'] = $employeeMap[$row['device_user_id']] ?? null;
        }
        unset($row);

        DB::transaction(function () use ($rows, $device) {
            if (!empty($rows)) {
                AttendanceLog::query()->upsert(
                    $rows,
                    ['attendance_device_id', 'device_user_id', 'punched_at'],
                    ['employee_id', 'event_type', 'raw', 'updated_at']
                );
            }

            $device->forceFill(['last_sync_at' => now()])->save();
        });

        return [
            'processed' => count($attendances),
            'saved' => count($rows),
            'filtered_before_last_sync' => max(0, count($attendances) - count($rows)),
        ];
    }
}
