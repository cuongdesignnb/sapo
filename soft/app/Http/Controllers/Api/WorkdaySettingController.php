<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkdaySetting;
use Illuminate\Http\Request;

class WorkdaySettingController extends Controller
{
    private function defaultWeekDays(): array
    {
        return [
            'mon' => true,
            'tue' => true,
            'wed' => true,
            'thu' => true,
            'fri' => true,
            'sat' => true,
            'sun' => false,
        ];
    }

    public function show(Request $request)
    {
        $warehouseId = $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null;

        $setting = WorkdaySetting::query()->where('warehouse_id', $warehouseId)->first();
        if (!$setting) {
            $setting = new WorkdaySetting([
                'warehouse_id' => $warehouseId,
                'week_days' => $this->defaultWeekDays(),
                'status' => 'active',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $setting,
        ]);
    }

    public function upsert(Request $request)
    {
        $data = $request->validate([
            'warehouse_id' => ['nullable', 'integer'],
            'week_days' => ['required', 'array'],
            'week_days.mon' => ['required', 'boolean'],
            'week_days.tue' => ['required', 'boolean'],
            'week_days.wed' => ['required', 'boolean'],
            'week_days.thu' => ['required', 'boolean'],
            'week_days.fri' => ['required', 'boolean'],
            'week_days.sat' => ['required', 'boolean'],
            'week_days.sun' => ['required', 'boolean'],
            'status' => ['nullable', 'string', 'max:30'],
        ]);

        $warehouseId = $data['warehouse_id'] ?? null;
        $userId = auth()->id();

        $setting = WorkdaySetting::query()->firstOrNew(['warehouse_id' => $warehouseId]);
        $setting->warehouse_id = $warehouseId;
        $setting->week_days = $data['week_days'];
        $setting->status = $data['status'] ?? ($setting->exists ? $setting->status : 'active');

        if (!$setting->exists) {
            $setting->created_by = $userId;
        }
        $setting->updated_by = $userId;
        $setting->save();

        return response()->json([
            'success' => true,
            'message' => 'Đã lưu ngày làm việc trong tuần',
            'data' => $setting,
        ]);
    }
}
