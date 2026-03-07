<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayrollSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayrollSettingController extends Controller
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

        $setting = PayrollSetting::query()
            ->where('warehouse_id', $warehouseId)
            ->first();

        if (!$setting) {
            return response()->json([
                'success' => true,
                'data' => [
                    'warehouse_id' => $warehouseId,
                    'pay_cycle' => 'monthly',
                    'start_day' => 26,
                    'end_day' => 25,
                    'start_in_prev_month' => true,
                    'pay_day' => 5,
                    'default_recalculate_timekeeping' => true,
                    'auto_generate_enabled' => false,
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
            'pay_cycle' => ['nullable', 'string', Rule::in(['monthly', 'weekly', 'biweekly'])],

            'start_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'end_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'start_in_prev_month' => ['nullable', 'boolean'],
            'pay_day' => ['nullable', 'integer', 'min:1', 'max:31'],

            'default_recalculate_timekeeping' => ['nullable', 'boolean'],
            'auto_generate_enabled' => ['nullable', 'boolean'],

            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $userId = $request->user()?->id;

        $setting = PayrollSetting::query()->updateOrCreate(
            ['warehouse_id' => $warehouseId],
            [
                'warehouse_id' => $warehouseId,
                'pay_cycle' => $data['pay_cycle'] ?? 'monthly',
                'start_day' => $data['start_day'] ?? 26,
                'end_day' => $data['end_day'] ?? 25,
                'start_in_prev_month' => $data['start_in_prev_month'] ?? true,
                'pay_day' => $data['pay_day'] ?? 5,
                'default_recalculate_timekeeping' => $data['default_recalculate_timekeeping'] ?? true,
                'auto_generate_enabled' => $data['auto_generate_enabled'] ?? false,
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
            'message' => 'Đã lưu thiết lập tính lương',
            'data' => $setting,
        ]);
    }
}
