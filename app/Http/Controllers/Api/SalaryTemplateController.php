<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalaryTemplate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalaryTemplateController extends Controller
{
    public function index()
    {
        $templates = SalaryTemplate::query()
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['fixed', 'hourly', 'monthly_commission'])],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        $template = SalaryTemplate::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'base_salary' => $data['base_salary'] ?? 0,
            'description' => $data['description'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tạo mẫu lương thành công.',
            'data' => $template,
        ]);
    }

    public function update(Request $request, SalaryTemplate $salaryTemplate)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['fixed', 'hourly', 'monthly_commission'])],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        $salaryTemplate->update([
            'name' => $data['name'],
            'type' => $data['type'],
            'base_salary' => $data['base_salary'] ?? 0,
            'description' => $data['description'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật mẫu lương thành công.',
            'data' => $salaryTemplate->fresh(),
        ]);
    }

    public function destroy(SalaryTemplate $salaryTemplate)
    {
        $salaryTemplate->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa mẫu lương thành công.',
        ]);
    }
}