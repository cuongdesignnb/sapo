<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    /**
     * Display a listing of units
     */
    public function index(Request $request)
    {
        $query = Unit::query();

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('note', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = $request->get('per_page', 20);
        $units = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $units->items(),
            'pagination' => [
                'current_page' => $units->currentPage(),
                'last_page' => $units->lastPage(),
                'per_page' => $units->perPage(),
                'total' => $units->total(),
                'from' => $units->firstItem(),
                'to' => $units->lastItem()
            ]
        ]);
    }

    /**
     * Show a specific unit
     */
    public function show($id)
    {
        $unit = Unit::find($id);

        if (!$unit) {
            return response()->json([
                'success' => false,
                'message' => 'Đơn vị tính không tồn tại'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $unit
        ]);
    }

    /**
     * Store a new unit
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:units,name',
            'note' => 'nullable|string'
        ], [
            'name.required' => 'Tên đơn vị tính là bắt buộc',
            'name.unique' => 'Tên đơn vị tính đã tồn tại'
        ]);

        $unit = Unit::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tạo đơn vị tính thành công',
            'data' => $unit
        ], 201);
    }

    /**
     * Update a unit
     */
    public function update(Request $request, $id)
    {
        $unit = Unit::find($id);

        if (!$unit) {
            return response()->json([
                'success' => false,
                'message' => 'Đơn vị tính không tồn tại'
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:units,name,' . $id,
            'note' => 'nullable|string'
        ], [
            'name.required' => 'Tên đơn vị tính là bắt buộc',
            'name.unique' => 'Tên đơn vị tính đã tồn tại'
        ]);

        $unit->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật đơn vị tính thành công',
            'data' => $unit
        ]);
    }

    /**
     * Delete a unit
     */
    public function destroy($id)
    {
        $unit = Unit::find($id);

        if (!$unit) {
            return response()->json([
                'success' => false,
                'message' => 'Đơn vị tính không tồn tại'
            ], 404);
        }

        // Check if unit is being used by products
        if ($unit->products()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa đơn vị tính đang được sử dụng bởi sản phẩm'
            ], 422);
        }

        $unit->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa đơn vị tính thành công'
        ]);
    }

    /**
     * Bulk delete units
     */
    public function bulkDelete(Request $request)
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'integer|exists:units,id'
            ]);

            // Check if any unit is being used by products
            $unitsInUse = Unit::whereIn('id', $request->ids)
                ->whereHas('products')
                ->pluck('name');

            if ($unitsInUse->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể xóa đơn vị tính đang được sử dụng: ' . $unitsInUse->implode(', ')
                ], 422);
            }

            $deletedCount = Unit::whereIn('id', $request->ids)->delete();

            return response()->json([
                'success' => true,
                'message' => "Đã xóa {$deletedCount} đơn vị tính thành công"
            ]);

        } catch (\Exception $e) {
            \Log::error('Unit bulk delete error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa đơn vị tính: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export units to CSV
     */
    public function export(Request $request)
    {
        try {
            $query = Unit::query();
            
            // Apply filters
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('note', 'like', "%{$search}%");
                });
            }
            
            if ($request->has('selected_ids') && !empty($request->selected_ids)) {
                $ids = is_array($request->selected_ids) ? $request->selected_ids : explode(',', $request->selected_ids);
                $query->whereIn('id', $ids);
            }
            
            $units = $query->get();
            
            // Create CSV content
            $csvData = [];
            $csvData[] = ['STT', 'name', 'note', 'created_at'];
            
            foreach ($units as $index => $unit) {
                $csvData[] = [
                    $index + 1,
                    $unit->name ?? '',
                    $unit->note ?? '',
                    $unit->created_at ? $unit->created_at->format('Y-m-d H:i:s') : ''
                ];
            }
            
            $filename = 'units_export_' . date('Y_m_d_H_i_s') . '.csv';
            
            return response()->streamDownload(function() use ($csvData) {
                $file = fopen('php://output', 'w');
                
                // UTF-8 BOM for Excel
                fwrite($file, "\xEF\xBB\xBF");
                
                foreach ($csvData as $row) {
                    fputcsv($file, $row);
                }
                fclose($file);
            }, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Export error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xuất file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import units from CSV
     */
    public function import(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:csv,txt|max:10240',
            ], [
                'file.required' => 'Vui lòng chọn file để nhập',
                'file.mimes' => 'File phải có định dạng CSV',
                'file.max' => 'File không được vượt quá 10MB'
            ]);

            $file = $request->file('file');
            $path = $file->getRealPath();
            
            $csvData = array_map('str_getcsv', file($path));
            
            if (!empty($csvData[0])) {
                $csvData[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $csvData[0][0]);
            }
            
            $headers = array_shift($csvData);
            $normalizedHeaders = array_map(function($header) {
                return strtolower(trim($header));
            }, $headers);
            
            $importedCount = 0;
            $updatedCount = 0;
            $errors = [];
            
            foreach ($csvData as $rowIndex => $row) {
                $actualRowNumber = $rowIndex + 2;
                
                try {
                    if (empty(array_filter($row))) {
                        continue;
                    }
                    
                    $data = array_combine($normalizedHeaders, $row);
                    
                    $unitData = [
                        'name' => $this->getFieldValue($data, ['name']),
                        'note' => $this->getFieldValue($data, ['note']),
                    ];
                    
                    if (empty($unitData['name'])) {
                        $errors[] = "Dòng {$actualRowNumber}: Tên đơn vị tính không được để trống";
                        continue;
                    }
                    
                    $existingUnit = Unit::where('name', $unitData['name'])->first();
                    
                    if ($existingUnit) {
                        $existingUnit->update($unitData);
                        $updatedCount++;
                    } else {
                        Unit::create($unitData);
                        $importedCount++;
                    }
                    
                } catch (\Exception $e) {
                    $errors[] = "Dòng {$actualRowNumber}: " . $e->getMessage();
                }
            }
            
            $message = "Import thành công! Tạo mới: {$importedCount}, Cập nhật: {$updatedCount}";
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'imported_count' => $importedCount,
                    'updated_count' => $updatedCount,
                    'errors' => $errors
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Import error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi import file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download import template
     */
    public function downloadTemplate()
    {
        $headers = ['STT', 'name', 'note'];
        $sampleData = [
            ['1', 'Cái', 'Đơn vị đếm cho sản phẩm rời'],
            ['2', 'Hộp', 'Đơn vị đóng gói'],
            ['3', 'Kg', 'Kilogram - đơn vị khối lượng']
        ];

        $filename = 'units_import_template.csv';

        return response()->streamDownload(function() use ($headers, $sampleData) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, $headers);
            foreach ($sampleData as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Helper method to get field value from CSV data
     */
    private function getFieldValue($data, $possibleKeys, $default = null)
    {
        foreach ($possibleKeys as $key) {
            if (isset($data[$key]) && !empty(trim($data[$key]))) {
                return trim($data[$key]);
            }
        }
        return $default;
    }
}