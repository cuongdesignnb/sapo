<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories
     */
    public function index(Request $request)
    {
        $query = Category::with('parent', 'children');

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('note', 'like', "%{$search}%");
            });
        }

        // Filter by parent
        if ($request->has('parent_id') && $request->parent_id !== '') {
            if ($request->parent_id === 'null') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $request->parent_id);
            }
        }

        // Sort
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = $request->get('per_page', 20);
        $categories = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $categories->items(),
            'pagination' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
                'from' => $categories->firstItem(),
                'to' => $categories->lastItem()
            ]
        ]);
    }

    /**
     * Show a specific category
     */
    public function show($id)
    {
        $category = Category::with('parent', 'children')->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Danh mục không tồn tại'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    /**
     * Store a new category
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'parent_id' => 'nullable|exists:categories,id',
            'note' => 'nullable|string'
        ], [
            'name.required' => 'Tên danh mục là bắt buộc',
            'name.unique' => 'Tên danh mục đã tồn tại',
            'parent_id.exists' => 'Danh mục cha không tồn tại'
        ]);

        $category = Category::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tạo danh mục thành công',
            'data' => $category->load('parent')
        ], 201);
    }

    /**
     * Update a category
     */
    public function update(Request $request, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Danh mục không tồn tại'
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $id,
            'parent_id' => 'nullable|exists:categories,id',
            'note' => 'nullable|string'
        ], [
            'name.required' => 'Tên danh mục là bắt buộc',
            'name.unique' => 'Tên danh mục đã tồn tại',
            'parent_id.exists' => 'Danh mục cha không tồn tại'
        ]);

        // Prevent setting parent to itself or its children
        if ($validated['parent_id'] == $id) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể đặt danh mục làm cha của chính nó'
            ], 422);
        }

        $category->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật danh mục thành công',
            'data' => $category->load('parent')
        ]);
    }

    /**
     * Delete a category
     */
    public function destroy($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Danh mục không tồn tại'
            ], 404);
        }

        // Check if category has children
        if ($category->children()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa danh mục có danh mục con'
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa danh mục thành công'
        ]);
    }

    /**
     * Bulk delete categories
     */
    public function bulkDelete(Request $request)
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'integer|exists:categories,id'
            ]);

            // Check if any category has children
            $categoriesWithChildren = Category::whereIn('id', $request->ids)
                ->whereHas('children')
                ->pluck('name');

            if ($categoriesWithChildren->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể xóa danh mục có danh mục con: ' . $categoriesWithChildren->implode(', ')
                ], 422);
            }

            $deletedCount = Category::whereIn('id', $request->ids)->delete();

            return response()->json([
                'success' => true,
                'message' => "Đã xóa {$deletedCount} danh mục thành công"
            ]);

        } catch (\Exception $e) {
            \Log::error('Category bulk delete error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa danh mục: ' . $e->getMessage()
            ], 500);
        }
    }
}