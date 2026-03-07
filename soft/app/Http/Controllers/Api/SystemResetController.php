<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemResetController extends Controller
{
    /**
     * Reset toàn bộ dữ liệu kinh doanh — giữ lại users, roles, warehouses, config.
     * Chỉ super_admin mới có quyền gọi.
     */
    public function resetAllData(Request $request)
    {
        $request->validate([
            'confirm' => 'required|string|in:RESET_ALL_DATA',
        ], [
            'confirm.in' => 'Bạn phải nhập đúng "RESET_ALL_DATA" để xác nhận.',
        ]);

        try {
            // Tắt kiểm tra FK tạm thời
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            // ========== 1. Đơn hàng & liên quan ==========
            $this->deleteAllIfExists([
                'product_serial_histories',
                'product_serials',
                'order_return_items',
                'order_returns',
                'order_status_history',
                'order_payments',
                'order_shippings',
                'order_items',
                'orders',
            ]);

            // ========== 2. Nhập hàng & trả hàng ==========
            $this->deleteAllIfExists([
                'purchase_return_order_items',
                'purchase_return_orders',
                'purchase_return_receipts',
                'purchase_receipt_items',
                'purchase_receipts',
                'purchase_order_items',
                'purchase_orders',
            ]);

            // ========== 3. Kho & sản phẩm ==========
            $this->deleteAllIfExists([
                'stock_adjustment_items',
                'stock_adjustments',
                'stock_export_items',
                'stock_exports',
                'warehouse_transfer_items',
                'warehouse_transfers',
                'warehouse_products',
                'products',
            ]);

            // ========== 4. Khách hàng & Nhà cung cấp (nợ) ==========
            $this->deleteAllIfExists([
                'customer_debts',
                'customer_addresses',
                'customers',
                'supplier_debts',
                'supplier_contacts',
                'suppliers',
            ]);

            // ========== 5. Danh mục & đơn vị ==========
            $this->deleteAllIfExists([
                'categories',
                'units',
                'customer_groups',
                'supplier_groups',
            ]);

            // ========== 6. Thu chi ==========
            $this->deleteAllIfExists([
                'cash_receipt_transactions',
                'cash_receipts',
                'cash_payment_transactions',
                'cash_payments',
            ]);

            // ========== 7. Shipping logs ==========
            $this->deleteAllIfExists([
                'shipping_logs',
            ]);

            // ========== 8. Hoá đơn ==========
            $this->deleteAllIfExists([
                'invoice_payments',
                'invoice_items',
                'invoices',
            ]);

            // Bật lại FK
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            return response()->json([
                'success' => true,
                'message' => 'Đã xoá toàn bộ dữ liệu kinh doanh. Hệ thống sẵn sàng nhập dữ liệu mới.',
                'kept' => [
                    'users' => 'Tài khoản người dùng',
                    'roles' => 'Phân quyền',
                    'warehouses' => 'Danh sách kho',
                    'shipping_providers' => 'Đối tác vận chuyển',
                    'config' => 'Cấu hình hệ thống',
                ],
            ]);

        } catch (\Exception $e) {
            // Đảm bảo bật lại FK dù lỗi
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi reset dữ liệu: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Xoá toàn bộ dữ liệu trong bảng nếu tồn tại (dùng DELETE thay vì TRUNCATE để tránh lỗi transaction)
     */
    private function deleteAllIfExists(array $tables): void
    {
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }
    }
}
