<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    private function timeToMinutes(?string $time): ?int
    {
        if (!$time) {
            return null;
        }

        $time = trim($time);
        if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $time, $m)) {
            return null;
        }

        $hours = (int) $m[1];
        $minutes = (int) $m[2];

        if ($hours < 0 || $hours > 23 || $minutes < 0 || $minutes > 59) {
            return null;
        }

        return $hours * 60 + $minutes;
    }

    private function inferIsOvernight(?string $startTime, ?string $endTime): bool
    {
        $start = $this->timeToMinutes($startTime);
        $end = $this->timeToMinutes($endTime);

        if ($start === null || $end === null) {
            return false;
        }

        return $end <= $start;
    }

    public function index(Request $request)
    {
        $query = Shift::query()->with(['warehouse:id,name']);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $shifts = $query->orderByDesc('id')->get();

        return response()->json([
            'success' => true,
            'data' => $shifts,
        ]);
    }

    public function show(Shift $shift)
    {
        $shift->load(['warehouse:id,name']);

        return response()->json([
            'success' => true,
            'data' => $shift,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'warehouse_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'checkin_start_time' => ['nullable', 'date_format:H:i', 'required_with:checkin_end_time'],
            'checkin_end_time' => ['nullable', 'date_format:H:i', 'required_with:checkin_start_time'],
            'allow_late_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'allow_early_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'rounding_minutes' => ['nullable', 'integer', 'min:1', 'max:240'],
            'is_overnight' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        $isOvernight = array_key_exists('is_overnight', $data)
            ? (bool) $data['is_overnight']
            : $this->inferIsOvernight($data['start_time'], $data['end_time']);

        $shift = Shift::create([
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'name' => $data['name'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'checkin_start_time' => $data['checkin_start_time'] ?? null,
            'checkin_end_time' => $data['checkin_end_time'] ?? null,
            'allow_late_minutes' => $data['allow_late_minutes'] ?? 0,
            'allow_early_minutes' => $data['allow_early_minutes'] ?? 0,
            'rounding_minutes' => $data['rounding_minutes'] ?? 15,
            'is_overnight' => $isOvernight,
            'status' => $data['status'] ?? 'active',
            'notes' => $data['notes'] ?? null,
        ]);

        $shift->load(['warehouse:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Tạo ca làm việc thành công',
            'data' => $shift,
        ]);
    }

    public function update(Request $request, Shift $shift)
    {
        $data = $request->validate([
            'warehouse_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'checkin_start_time' => ['nullable', 'date_format:H:i', 'required_with:checkin_end_time'],
            'checkin_end_time' => ['nullable', 'date_format:H:i', 'required_with:checkin_start_time'],
            'allow_late_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'allow_early_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'rounding_minutes' => ['nullable', 'integer', 'min:1', 'max:240'],
            'is_overnight' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        $isOvernight = array_key_exists('is_overnight', $data)
            ? (bool) $data['is_overnight']
            : $this->inferIsOvernight($data['start_time'], $data['end_time']);

        $shift->update([
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'name' => $data['name'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'checkin_start_time' => $data['checkin_start_time'] ?? $shift->checkin_start_time,
            'checkin_end_time' => $data['checkin_end_time'] ?? $shift->checkin_end_time,
            'allow_late_minutes' => $data['allow_late_minutes'] ?? $shift->allow_late_minutes,
            'allow_early_minutes' => $data['allow_early_minutes'] ?? $shift->allow_early_minutes,
            'rounding_minutes' => $data['rounding_minutes'] ?? $shift->rounding_minutes,
            'is_overnight' => $isOvernight,
            'status' => $data['status'] ?? $shift->status,
            'notes' => $data['notes'] ?? $shift->notes,
        ]);

        $shift->load(['warehouse:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật ca làm việc thành công',
            'data' => $shift,
        ]);
    }

    public function destroy(Shift $shift)
    {
        $shift->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa ca làm việc thành công',
        ]);
    }

    public function toggle(Shift $shift)
    {
        $shift->update([
            'status' => $shift->status === 'active' ? 'inactive' : 'active',
        ]);

        $shift->load(['warehouse:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật trạng thái ca làm việc',
            'data' => $shift,
        ]);
    }
}
