<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TimekeepingSetting;
use App\Models\TimekeepingRecord;
use App\Services\TimekeepingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TimekeepingRecordController extends Controller
{
    public function __construct(private readonly TimekeepingService $timekeepingService)
    {
    }

    public function index(Request $request)
    {
        $query = TimekeepingRecord::query()->with([
            'employee:id,code,name',
            'shift:id,name,start_time,end_time',
            'warehouse:id,name',
            'schedule:id,employee_id,work_date,slot,shift_name,start_time,end_time,status',
        ]);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('work_date', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('work_date', '<=', $request->date('to'));
        }

        $perPage = (int) $request->get('per_page', 50);
        $records = $query->orderByDesc('work_date')->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $records->items(),
            'pagination' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'from' => $records->firstItem(),
                'to' => $records->lastItem(),
            ],
        ]);
    }

    public function recalculate(Request $request)
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
            'employee_id' => ['nullable', 'integer'],
        ]);

        $result = $this->timekeepingService->recalculateForRange(
            Carbon::parse($data['from']),
            Carbon::parse($data['to']),
            $data['employee_id'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Đã tính lại công/đi muộn/OT theo lịch và log chấm công',
            'data' => $result,
        ]);
    }

    public function show(TimekeepingRecord $timekeepingRecord)
    {
        $timekeepingRecord->load([
            'employee:id,code,name',
            'shift:id,name,start_time,end_time,allow_late_minutes,allow_early_minutes',
            'warehouse:id,name',
            'schedule:id,employee_id,work_date,slot,shift_id,shift_name,start_time,end_time,status,warehouse_id',
        ]);

        return response()->json([
            'success' => true,
            'data' => $timekeepingRecord,
        ]);
    }

    /**
     * Upsert manual timekeeping by schedule.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_work_schedule_id' => ['required', 'integer', 'exists:employee_work_schedules,id'],
            'attendance_type' => ['nullable', Rule::in(['work', 'leave_paid', 'leave_unpaid'])],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'ot_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'notes' => ['nullable', 'string'],
        ]);

        $schedule = \App\Models\EmployeeWorkSchedule::query()->with(['shift'])->findOrFail($data['employee_work_schedule_id']);

        $setting = TimekeepingSetting::query()
            ->where('status', 'active')
            ->where(function ($q) use ($schedule) {
                $q->where('warehouse_id', $schedule->warehouse_id)->orWhereNull('warehouse_id');
            })
            ->orderByRaw('warehouse_id is null')
            ->first();

        $useShiftAllowances = (bool) ($setting?->use_shift_allowances ?? true);
        $lateGrace = (int) ($setting?->late_grace_minutes ?? 0);
        $earlyGrace = (int) ($setting?->early_grace_minutes ?? 0);
        $otRounding = (int) ($setting?->ot_rounding_minutes ?? 0);
        $otAfter = (int) ($setting?->ot_after_minutes ?? 0);

        $attendanceType = (string) ($data['attendance_type'] ?? 'work');
        $checkInAt = null;
        $checkOutAt = null;

        if ($attendanceType === 'work') {
            if (!empty($data['check_in_time'])) {
                $checkInAt = Carbon::parse($schedule->work_date->toDateString() . ' ' . $data['check_in_time']);
            }
            if (!empty($data['check_out_time'])) {
                $checkOutAt = Carbon::parse($schedule->work_date->toDateString() . ' ' . $data['check_out_time']);
            }
        }

        $scheduleStart = $this->buildScheduleDateTime($schedule->work_date, $schedule->start_time, $schedule->shift?->start_time);
        $scheduleEnd = $this->buildScheduleDateTime($schedule->work_date, $schedule->end_time, $schedule->shift?->end_time);
        if ($scheduleStart && $scheduleEnd && $scheduleEnd->lessThanOrEqualTo($scheduleStart)) {
            $scheduleEnd = $scheduleEnd->copy()->addDay();
        }

        // Handle overnight shifts for manual time-only inputs (e.g. 22:00 -> 02:00).
        if ($scheduleStart && $scheduleEnd && $scheduleEnd->greaterThan($scheduleStart)) {
          if ($checkInAt && $checkInAt->lessThan($scheduleStart)) {
              $checkInAt = $checkInAt->copy()->addDay();
          }
          if ($checkOutAt && $checkOutAt->lessThan($scheduleStart)) {
              $checkOutAt = $checkOutAt->copy()->addDay();
          }
        }

        if ($checkInAt && $checkOutAt && $checkOutAt->lessThanOrEqualTo($checkInAt)) {
            $checkOutAt = $checkOutAt->copy()->addDay();
        }

        // Derived minutes
        $lateMinutes = 0;
        $earlyMinutes = 0;
        $workedMinutes = 0;
        $otMinutes = (int) ($data['ot_minutes'] ?? 0);

        $allowLate = (int) ($schedule->shift?->allow_late_minutes ?? 0);
        $allowEarly = (int) ($schedule->shift?->allow_early_minutes ?? 0);
        if (!$useShiftAllowances) {
            $allowLate = $lateGrace;
            $allowEarly = $earlyGrace;
        }

        if ($checkInAt && $checkOutAt) {
            $workedMinutes = max(0, $checkOutAt->diffInMinutes($checkInAt, false));
        }

        if ($scheduleStart && $checkInAt) {
            $diff = $checkInAt->diffInMinutes($scheduleStart, false);
            $lateMinutes = max(0, $diff - $allowLate);
        }

        if ($scheduleEnd && $checkOutAt) {
            $diffEarly = $scheduleEnd->diffInMinutes($checkOutAt, false);
            $earlyMinutes = max(0, $diffEarly - $allowEarly);

            $diffOt = $checkOutAt->diffInMinutes($scheduleEnd, false);
            $derivedOt = max(0, $diffOt);
            $derivedOt = max(0, $derivedOt - $otAfter);
            if ($otRounding > 0) {
                $derivedOt = (int) (floor($derivedOt / $otRounding) * $otRounding);
            }
            $otMinutes = max($otMinutes, $derivedOt);
        }

        $record = TimekeepingRecord::query()->updateOrCreate(
            ['employee_work_schedule_id' => $schedule->id],
            [
                'employee_id' => $schedule->employee_id,
                'warehouse_id' => $schedule->warehouse_id,
                'shift_id' => $schedule->shift_id,
                'work_date' => $schedule->work_date,
                'slot' => $schedule->slot ?? 1,
                'scheduled_start_at' => $scheduleStart,
                'scheduled_end_at' => $scheduleEnd,
                'check_in_at' => $checkInAt,
                'check_out_at' => $checkOutAt,
                'source' => 'manual',
                'attendance_type' => $attendanceType,
                'manual_override' => true,
                'late_minutes' => $lateMinutes,
                'early_minutes' => $earlyMinutes,
                'ot_minutes' => $otMinutes,
                'worked_minutes' => $workedMinutes,
                'notes' => $data['notes'] ?? null,
            ]
        );

        $record->load([
            'employee:id,code,name',
            'shift:id,name,start_time,end_time,allow_late_minutes,allow_early_minutes',
            'warehouse:id,name',
            'schedule:id,employee_id,work_date,slot,shift_id,shift_name,start_time,end_time,status,warehouse_id',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã lưu chấm công',
            'data' => $record,
        ]);
    }

    public function update(Request $request, TimekeepingRecord $timekeepingRecord)
    {
        // Update via same store logic but with schedule from record
        $request->merge(['employee_work_schedule_id' => $timekeepingRecord->employee_work_schedule_id]);
        return $this->store($request);
    }

    private function buildScheduleDateTime($workDate, $scheduleTime, $fallbackShiftTime): ?Carbon
    {
        $time = $scheduleTime ?? $fallbackShiftTime;
        if (!$time) {
            return null;
        }

        $timeStr = is_string($time) ? $time : (string) $time;

        if ($workDate instanceof Carbon) {
            $date = $workDate->copy();
        } else {
            $workDateStr = is_string($workDate) ? trim($workDate) : (string) $workDate;
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $workDateStr)) {
                $date = Carbon::createFromFormat('d/m/Y', $workDateStr);
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $workDateStr)) {
                $date = Carbon::parse(substr($workDateStr, 0, 10));
            } else {
                $date = Carbon::parse($workDateStr);
            }
        }

        return $date->copy()->startOfDay()->setTimeFromTimeString($timeStr);
    }
}
