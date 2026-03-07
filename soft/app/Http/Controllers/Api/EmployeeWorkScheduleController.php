<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeWorkSchedule;
use Illuminate\Http\Request;

class EmployeeWorkScheduleController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeeWorkSchedule::query()->with([
            'employee:id,code,name',
            'warehouse:id,name',
            'shift:id,name,start_time,end_time',
            'timekeepingRecord:id,employee_work_schedule_id,attendance_type,manual_override,scheduled_start_at,scheduled_end_at,check_in_at,check_out_at,source,late_minutes,early_minutes,ot_minutes,worked_minutes,notes',
        ]);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        if ($request->filled('shift_id')) {
            $query->where('shift_id', $request->integer('shift_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('work_date', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('work_date', '<=', $request->date('to'));
        }

        $perPage = (int) $request->get('per_page', 20);
        $schedules = $query->orderByDesc('work_date')->orderByDesc('slot')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $schedules->items(),
            'pagination' => [
                'current_page' => $schedules->currentPage(),
                'last_page' => $schedules->lastPage(),
                'per_page' => $schedules->perPage(),
                'total' => $schedules->total(),
                'from' => $schedules->firstItem(),
                'to' => $schedules->lastItem(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'work_date' => ['required', 'date'],
            'slot' => ['nullable', 'integer', 'min:1', 'max:20'],
            'warehouse_id' => ['nullable', 'integer'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'start_time' => ['nullable'],
            'end_time' => ['nullable'],
            'shift_name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        $slot = (int) ($data['slot'] ?? 1);

        $schedule = EmployeeWorkSchedule::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'work_date' => $data['work_date'], 'slot' => $slot],
            [
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'shift_id' => $data['shift_id'] ?? null,
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'shift_name' => $data['shift_name'] ?? null,
                'status' => $data['status'] ?? 'planned',
                'notes' => $data['notes'] ?? null,
            ]
        );

        $schedule->load([
            'employee:id,code,name',
            'warehouse:id,name',
            'shift:id,name,start_time,end_time',
            'timekeepingRecord:id,employee_work_schedule_id,attendance_type,manual_override,scheduled_start_at,scheduled_end_at,check_in_at,check_out_at,source,late_minutes,early_minutes,ot_minutes,worked_minutes,notes',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lưu lịch làm việc thành công',
            'data' => $schedule,
        ]);
    }

    public function update(Request $request, EmployeeWorkSchedule $schedule)
    {
        $data = $request->validate([
            'warehouse_id' => ['nullable', 'integer'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'start_time' => ['nullable'],
            'end_time' => ['nullable'],
            'shift_name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        $schedule->update([
            'warehouse_id' => $data['warehouse_id'] ?? $schedule->warehouse_id,
            'shift_id' => $data['shift_id'] ?? $schedule->shift_id,
            'start_time' => $data['start_time'] ?? $schedule->start_time,
            'end_time' => $data['end_time'] ?? $schedule->end_time,
            'shift_name' => $data['shift_name'] ?? $schedule->shift_name,
            'status' => $data['status'] ?? $schedule->status,
            'notes' => $data['notes'] ?? $schedule->notes,
        ]);

        $schedule->load([
            'employee:id,code,name',
            'warehouse:id,name',
            'shift:id,name,start_time,end_time',
            'timekeepingRecord:id,employee_work_schedule_id,attendance_type,manual_override,scheduled_start_at,scheduled_end_at,check_in_at,check_out_at,source,late_minutes,early_minutes,ot_minutes,worked_minutes,notes',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật lịch làm việc thành công',
            'data' => $schedule,
        ]);
    }

    public function destroy(EmployeeWorkSchedule $schedule)
    {
        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa lịch làm việc thành công',
        ]);
    }
}
