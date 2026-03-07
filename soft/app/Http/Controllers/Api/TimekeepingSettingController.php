<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TimekeepingSetting;
use Illuminate\Http\Request;

class TimekeepingSettingController extends Controller
{
    private function resolveWarehouseId(Request $request): ?int
    {
        if ($request->filled('warehouse_id')) {
            return $request->integer('warehouse_id');
        }

        return null;
    }

    public function show(Request $request)
    {
        $warehouseId = $this->resolveWarehouseId($request);

        $setting = TimekeepingSetting::query()
            ->where('warehouse_id', $warehouseId)
            ->first();

        if (!$setting) {
            return response()->json([
                'success' => true,
                'data' => [
                    'warehouse_id' => $warehouseId,
                    'standard_hours_per_day' => 8,
                    'use_shift_allowances' => true,
                    'late_grace_minutes' => 0,
                    'early_grace_minutes' => 0,
                    'allow_multiple_shifts_one_inout' => false,
                    'enforce_shift_checkin_window' => false,
                    'ot_rounding_minutes' => 0,
                    'ot_after_minutes' => 0,
                    'status' => 'active',
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $setting,
        ]);
    }

    public function upsert(Request $request)
    {
        $warehouseId = $this->resolveWarehouseId($request);

        $data = $request->validate([
            'warehouse_id' => ['nullable', 'integer'],

            'standard_hours_per_day' => ['nullable', 'numeric', 'min:0', 'max:24'],

            'use_shift_allowances' => ['nullable', 'boolean'],
            'late_grace_minutes' => ['nullable', 'integer', 'min:0', 'max:300'],
            'early_grace_minutes' => ['nullable', 'integer', 'min:0', 'max:300'],

            'allow_multiple_shifts_one_inout' => ['nullable', 'boolean'],
            'enforce_shift_checkin_window' => ['nullable', 'boolean'],

            'ot_rounding_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'ot_after_minutes' => ['nullable', 'integer', 'min:0', 'max:300'],

            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $userId = $request->user()?->id;

        $setting = TimekeepingSetting::query()->updateOrCreate(
            ['warehouse_id' => $warehouseId],
            [
                'warehouse_id' => $warehouseId,
                'standard_hours_per_day' => $data['standard_hours_per_day'] ?? 8,

                'use_shift_allowances' => $data['use_shift_allowances'] ?? true,
                'late_grace_minutes' => $data['late_grace_minutes'] ?? 0,
                'early_grace_minutes' => $data['early_grace_minutes'] ?? 0,

                'allow_multiple_shifts_one_inout' => $data['allow_multiple_shifts_one_inout'] ?? false,
                'enforce_shift_checkin_window' => $data['enforce_shift_checkin_window'] ?? false,

                'ot_rounding_minutes' => $data['ot_rounding_minutes'] ?? 0,
                'ot_after_minutes' => $data['ot_after_minutes'] ?? 0,

                'status' => $data['status'] ?? 'active',
                'updated_by' => $userId,
            ]
        );

        if (!$setting->created_by) {
            $setting->created_by = $userId;
            $setting->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã lưu thiết lập chấm công',
            'data' => $setting,
        ]);
    }
}
