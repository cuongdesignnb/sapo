<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class PrintController extends Controller
{
    public function printOrder($id)
    {
        try {
            $order = Order::with([
                'customer:id,code,name,phone,email,address',
                'warehouse:id,code,name,address,phone',
                'cashier:id,name,email',
                'items.product:id,sku,name,retail_price,unit',
                'payments'
            ])->findOrFail($id);

            return view('orders.print', compact('order'));

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể in đơn hàng: ' . $e->getMessage()
            ], 500);
        }
    }
}