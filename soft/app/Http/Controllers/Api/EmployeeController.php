<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EmployeeController extends Controller
{
    private function generateEmployeeCode(): string
    {
        $base = ((int) Employee::query()->max('id')) + 1;

        for ($i = 0; $i < 20; $i++) {
            $candidate = 'NV' . str_pad((string) ($base + $i), 6, '0', STR_PAD_LEFT);
            if (!Employee::query()->where('code', $candidate)->exists()) {
                return $candidate;
            }
        }

        // Fallback
        return 'NV' . now()->format('ymdHis');
    }

    public function index(Request $request)
    {
        $query = Employee::query()->with([
            'warehouse:id,name',
            'workWarehouses:id,name',
            'salaryConfig:id,employee_id,salary_template_id',
            'salaryConfig.template:id,name',
        ]);

        if ($request->filled('search')) {
            $search = trim((string) $request->get('search'));
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('attendance_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('id_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        if ($request->filled('work_warehouse_id')) {
            $workWarehouseId = $request->integer('work_warehouse_id');
            $query->whereHas('workWarehouses', function ($q) use ($workWarehouseId) {
                $q->where('warehouses.id', $workWarehouseId);
            });
        }

        if ($request->filled('department')) {
            $query->where('department', (string) $request->get('department'));
        }

        if ($request->filled('title')) {
            $query->where('title', (string) $request->get('title'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->get('status'));
        }

        $perPage = (int) $request->get('per_page', 20);
        $employees = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $employees->items(),
            'pagination' => [
                'current_page' => $employees->currentPage(),
                'last_page' => $employees->lastPage(),
                'per_page' => $employees->perPage(),
                'total' => $employees->total(),
                'from' => $employees->firstItem(),
                'to' => $employees->lastItem(),
            ],
        ]);
    }

    public function show(Employee $employee)
    {
        $employee->load(['warehouse:id,name', 'workWarehouses:id,name', 'salaryConfig.template:id,name']);

        return response()->json([
            'success' => true,
            'data' => $employee,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:50', 'unique:employees,code'],
            'attendance_code' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'id_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:30'],
            'department' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'start_work_date' => ['nullable', 'date'],
            'warehouse_id' => ['nullable', 'integer'],
            'work_warehouse_ids' => ['nullable', 'array'],
            'work_warehouse_ids.*' => ['integer'],
            'status' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        $code = $data['code'] ?? null;
        if (!$code) {
            $code = $this->generateEmployeeCode();
        }

        $employee = Employee::create([
            'code' => $code,
            'attendance_code' => $data['attendance_code'] ?? null,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'id_number' => $data['id_number'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'gender' => $data['gender'] ?? null,
            'department' => $data['department'] ?? null,
            'title' => $data['title'] ?? null,
            'start_work_date' => $data['start_work_date'] ?? null,
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'status' => $data['status'] ?? 'active',
            'notes' => $data['notes'] ?? null,
        ]);

        if (!empty($data['work_warehouse_ids']) && is_array($data['work_warehouse_ids'])) {
            $employee->workWarehouses()->sync(array_values(array_unique(array_map('intval', $data['work_warehouse_ids']))));
        }

        $employee->load(['warehouse:id,name', 'workWarehouses:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Tạo nhân viên thành công',
            'data' => $employee,
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:employees,code,' . $employee->id],
            'attendance_code' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'id_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:30'],
            'department' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'start_work_date' => ['nullable', 'date'],
            'warehouse_id' => ['nullable', 'integer'],
            'work_warehouse_ids' => ['nullable', 'array'],
            'work_warehouse_ids.*' => ['integer'],
            'status' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        $employee->update([
            'code' => $data['code'],
            'attendance_code' => $data['attendance_code'] ?? $employee->attendance_code,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'id_number' => $data['id_number'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'gender' => $data['gender'] ?? null,
            'department' => $data['department'] ?? null,
            'title' => $data['title'] ?? null,
            'start_work_date' => $data['start_work_date'] ?? null,
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'status' => $data['status'] ?? $employee->status,
            'notes' => $data['notes'] ?? null,
        ]);

        if (array_key_exists('work_warehouse_ids', $data)) {
            $ids = is_array($data['work_warehouse_ids'])
                ? array_values(array_unique(array_map('intval', $data['work_warehouse_ids'])))
                : [];
            $employee->workWarehouses()->sync($ids);
        }

        $employee->load(['warehouse:id,name', 'workWarehouses:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật nhân viên thành công',
            'data' => $employee,
        ]);
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa nhân viên thành công',
        ]);
    }

    public function uploadAvatar(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'avatar' => ['required', 'file', 'image', 'max:4096'],
        ]);

        $file = $data['avatar'];
        $path = $file->store('avatars/employees', 'public');
        $url = Storage::disk('public')->url($path);

        $employee->update([
            'avatar_path' => $url,
        ]);

        $employee->load(['warehouse:id,name', 'workWarehouses:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật ảnh nhân viên thành công',
            'data' => $employee,
        ]);
    }
}
