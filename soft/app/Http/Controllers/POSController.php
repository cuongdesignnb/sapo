<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class POSController extends Controller
{
    /**
     * Hiển thị trang POS
     */
    public function index()
    {
        $warehouses = Warehouse::where('status', 'active')->get(['id', 'code', 'name']);
        $defaultWarehouse = $warehouses->first();
        
        return view('pos.index', compact('warehouses', 'defaultWarehouse'));
    }

    /**
     * Tìm kiếm khách hàng cho POS
     */
    public function searchCustomers(Request $request)
    {
        try {
            $search = $request->get('search', '');
            $perPage = $request->get('per_page', 10);

            if (strlen($search) < 2) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'Search term too short'
                ]);
            }

            $customers = \App\Models\Customer::where(function($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
            })
            ->where('status', 'active')
            ->limit($perPage)
            ->get(['id', 'name', 'phone', 'code']);

            return response()->json([
                'success' => true,
                'data' => $customers,
                'message' => 'Customers retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error searching customers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tìm kiếm sản phẩm cho POS
     */
    public function searchProducts(Request $request)
    {
        try {
            $search = $request->get('search', '');
            $perPage = $request->get('per_page', 10);

            if (strlen($search) < 2) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'Search term too short'
                ]);
            }

            $products = \App\Models\Product::where(function($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%");
            })
            ->where('status', 'active')
            ->limit($perPage)
            ->get(['id', 'name', 'sku', 'retail_price', 'price']);

            return response()->json([
                'success' => true,
                'data' => $products,
                'message' => 'Products retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error searching products: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Hiển thị trang in hóa đơn
     */
    public function showPrint($id)
    {
        $order = Order::with([
            'customer',
            'warehouse', 
            'cashier',
            'items.product'
        ])->findOrFail($id);

        return view('pos.print', compact('order'));
    }
}