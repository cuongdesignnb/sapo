<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalaryTemplate;
use App\Models\SalaryTemplateItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalaryTemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = SalaryTemplate::query()->with(['items']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $templates = $query->orderByDesc('id')->get();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    public function show(SalaryTemplate $salaryTemplate)
    {
        $salaryTemplate->load(['items']);

        return response()->json([
            'success' => true,
            'data' => $salaryTemplate,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_salary' => ['nullable', 'numeric'],
            'standard_work_units' => ['nullable', 'numeric', 'min:0'],
            'half_day_threshold_hours' => ['nullable', 'numeric', 'min:0'],
            'overtime_hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.type' => ['required_with:items', 'string', 'max:30'],
            'items.*.name' => ['required_with:items', 'string', 'max:255'],
            'items.*.amount' => ['nullable', 'numeric'],
            'items.*.status' => ['nullable', 'string', 'max:30'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $template = DB::transaction(function () use ($data) {
            $template = SalaryTemplate::create([
                'name' => $data['name'],
                'base_salary' => $data['base_salary'] ?? 0,
                'standard_work_units' => $data['standard_work_units'] ?? 26,
                'half_day_threshold_hours' => $data['half_day_threshold_hours'] ?? 4.5,
                'overtime_hourly_rate' => $data['overtime_hourly_rate'] ?? 0,
                'status' => $data['status'] ?? 'active',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach (($data['items'] ?? []) as $item) {
                SalaryTemplateItem::create([
                    'salary_template_id' => $template->id,
                    'type' => $item['type'],
                    'name' => $item['name'],
                    'amount' => $item['amount'] ?? 0,
                    'status' => $item['status'] ?? 'active',
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $template;
        });

        $template->load(['items']);

        return response()->json([
            'success' => true,
            'message' => 'Tạo mẫu lương thành công',
            'data' => $template,
        ]);
    }

    public function update(Request $request, SalaryTemplate $salaryTemplate)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_salary' => ['nullable', 'numeric'],
            'standard_work_units' => ['nullable', 'numeric', 'min:0'],
            'half_day_threshold_hours' => ['nullable', 'numeric', 'min:0'],
            'overtime_hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.type' => ['required_with:items', 'string', 'max:30'],
            'items.*.name' => ['required_with:items', 'string', 'max:255'],
            'items.*.amount' => ['nullable', 'numeric'],
            'items.*.status' => ['nullable', 'string', 'max:30'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($salaryTemplate, $data) {
            $salaryTemplate->update([
                'name' => $data['name'],
                'base_salary' => $data['base_salary'] ?? $salaryTemplate->base_salary,
                'standard_work_units' => $data['standard_work_units'] ?? $salaryTemplate->standard_work_units,
                'half_day_threshold_hours' => $data['half_day_threshold_hours'] ?? $salaryTemplate->half_day_threshold_hours,
                'overtime_hourly_rate' => $data['overtime_hourly_rate'] ?? $salaryTemplate->overtime_hourly_rate,
                'status' => $data['status'] ?? $salaryTemplate->status,
                'notes' => $data['notes'] ?? $salaryTemplate->notes,
            ]);

            if (array_key_exists('items', $data)) {
                SalaryTemplateItem::query()->where('salary_template_id', $salaryTemplate->id)->delete();
                foreach (($data['items'] ?? []) as $item) {
                    SalaryTemplateItem::create([
                        'salary_template_id' => $salaryTemplate->id,
                        'type' => $item['type'],
                        'name' => $item['name'],
                        'amount' => $item['amount'] ?? 0,
                        'status' => $item['status'] ?? 'active',
                        'notes' => $item['notes'] ?? null,
                    ]);
                }
            }
        });

        $salaryTemplate->load(['items']);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật mẫu lương thành công',
            'data' => $salaryTemplate,
        ]);
    }

    public function destroy(SalaryTemplate $salaryTemplate)
    {
        DB::transaction(function () use ($salaryTemplate) {
            SalaryTemplateItem::query()->where('salary_template_id', $salaryTemplate->id)->delete();
            $salaryTemplate->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Xóa mẫu lương thành công',
        ]);
    }
}
