<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SupplierGroupController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerGroupController;
use App\Http\Controllers\Api\CustomerDebtController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\WarehouseProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderReturnController;
use App\Http\Controllers\Api\POSController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\PurchaseReceiptController;
use App\Http\Controllers\Api\PurchaseReturnOrderController;
use App\Http\Controllers\Api\PurchaseReturnReceiptController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Controllers\Api\ShippingProviderController;
use App\Http\Controllers\Api\CashReceiptTypeController;
use App\Http\Controllers\Api\CashPaymentTypeController;
use App\Http\Controllers\Api\CashReceiptController;
use App\Http\Controllers\Api\CashPaymentController;
use App\Http\Controllers\Api\CashLedgerController;
use App\Http\Controllers\Api\TwoFactorController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// =======================
// 🔓 PUBLIC ROUTES
// =======================
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/2fa/challenge', [TwoFactorController::class, 'challenge']);
});

Route::get('/test', function () {
    return response()->json([
        'message' => 'API is working',
        'timestamp' => now(),
        'version' => '1.0.0'
    ]);
});

// Test route for order returns (no auth needed for debugging)
Route::get('/test-order-returns', function () {
    try {
        $returns = \App\Models\OrderReturn::with(['order', 'customer', 'items'])->limit(5)->get();
        return response()->json([
            'success' => true,
            'message' => 'Order returns test successful',
            'count' => $returns->count(),
            'data' => $returns
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

Route::get('/orders/{order}/print', [App\Http\Controllers\Api\OrderController::class, 'print']);
Route::get('/shipping/{id}/print', [App\Http\Controllers\Api\ShippingController::class, 'printLabel'])->name('shipping.print');
Route::post('/shipping/print-bulk', [App\Http\Controllers\Api\ShippingController::class, 'printBulkLabels'])->name('shipping.print-bulk');

// =======================
// 🔐 PROTECTED ROUTES
// =======================
Route::middleware(['auth:sanctum', 'active.user', 'update.activity'])->group(function () {

    // 🔐 Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/switch-warehouse', [AuthController::class, 'switchWarehouse']);
    });

    // 🔐 Two-Factor Authentication
    Route::prefix('2fa')->group(function () {
        Route::get('/test', [TwoFactorController::class, 'test']);
        Route::get('/status', [TwoFactorController::class, 'status']);
        Route::post('/setup', [TwoFactorController::class, 'setup']);
        Route::get('/qr-code', [TwoFactorController::class, 'qrCode'])->name('2fa.qr-code');
        Route::post('/confirm', [TwoFactorController::class, 'confirm']);
        Route::post('/disable', [TwoFactorController::class, 'disable']);
        Route::post('/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes']);
    });
    // 🔐 Warehouse Switching
    // Warehouse Switching API
    Route::prefix('warehouse-switching')->group(function () {
        Route::get('/available', [App\Http\Controllers\Api\WarehouseSwitchingController::class, 'getAvailableWarehouses']);
        Route::get('/current', [App\Http\Controllers\Api\WarehouseSwitchingController::class, 'getCurrentWarehouse']);
        Route::post('/switch', [App\Http\Controllers\Api\WarehouseSwitchingController::class, 'switchWarehouse']);
        Route::delete('/clear', [App\Http\Controllers\Api\WarehouseSwitchingController::class, 'clearWarehouse']);
    });
    // DEBUG
    Route::get('/debug-auth', function () {
        return response()->json([
            'authenticated' => auth()->check(),
            'user' => auth()->user()?->only(['id', 'name', 'email']),
            'token_valid' => true
        ]);
    });
Route::prefix('dashboard')->group(function () {
    // Bỏ middleware permission tạm thời để test
    Route::get('/overview', [DashboardController::class, 'overview']);
    Route::get('/sales-trend', [DashboardController::class, 'salesTrend']);
    Route::get('/revenue-profit', [DashboardController::class, 'revenueProfit']);
    Route::get('/top-products', [DashboardController::class, 'topProducts']);
    Route::get('/low-stock-alerts', [DashboardController::class, 'lowStockAlerts']);
    Route::get('/customer-analysis', [DashboardController::class, 'customerAnalysis']);
});
    // ======================
    // PRODUCTS
    // ======================
    Route::prefix('products')->middleware(['permission:products.view'])->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/export', [ProductController::class, 'export']);
        Route::get('/stock-history/{id}', [ProductController::class, 'getStockHistory']);
        Route::get('/{product}', [ProductController::class, 'show']);
        Route::get('/{product}/edit', [ProductController::class, 'edit']);
        Route::get('/stats/overview', [ProductController::class, 'statistics']);

        Route::middleware(['permission:products.manage'])->group(function () {
            Route::post('/', [ProductController::class, 'store']);
            Route::put('/{product}', [ProductController::class, 'update']);
            Route::delete('/{product}', [ProductController::class, 'destroy']);
            Route::post('/bulk-delete', [ProductController::class, 'bulkDelete']);
            Route::post('/import', [ProductController::class, 'import']);
        });
    });

    // ======================
    // CATEGORIES
    // ======================
    Route::prefix('categories')->middleware(['permission:products.view'])->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('/{category}', [CategoryController::class, 'show']);

        Route::middleware(['permission:products.manage'])->group(function () {
            Route::post('/', [CategoryController::class, 'store']);
            Route::put('/{category}', [CategoryController::class, 'update']);
            Route::delete('/{category}', [CategoryController::class, 'destroy']);
        });
    });

    // ======================
    // UNITS
    // ======================
    Route::prefix('units')->middleware(['permission:products.view'])->group(function () {
        Route::get('/', [UnitController::class, 'index']);
        Route::get('/{unit}', [UnitController::class, 'show']);

        Route::middleware(['permission:products.manage'])->group(function () {
            Route::post('/', [UnitController::class, 'store']);
            Route::put('/{unit}', [UnitController::class, 'update']);
            Route::delete('/{unit}', [UnitController::class, 'destroy']);
        });
    });

    // ======================
    // SUPPLIERS
    // ======================
    Route::prefix('suppliers')->middleware(['permission:suppliers.view'])->group(function () {
        Route::get('/', [SupplierController::class, 'index']);
        Route::get('/{supplier}/purchase-history/export', [SupplierController::class, 'exportPurchaseHistory']);
        Route::get('/{supplier}', [SupplierController::class, 'show']);
        Route::get('/{supplier}/edit', [SupplierController::class, 'edit']);
        Route::get('/create', [SupplierController::class, 'create']);
        Route::get('/export/csv', [SupplierController::class, 'export']);
        Route::get('/{supplier}/purchase-history', [SupplierController::class, 'purchaseHistory']);
        Route::get('/{supplier}/debts', [SupplierController::class, 'debtHistory']);
        Route::get('/stats/overview', [SupplierController::class, 'statistics']);

        Route::middleware(['permission:suppliers.manage'])->group(function () {
            Route::post('/', [SupplierController::class, 'store']);
            Route::put('/{supplier}', [SupplierController::class, 'update']);
            Route::patch('/{supplier}', [SupplierController::class, 'update']);
            Route::delete('/{supplier}', [SupplierController::class, 'destroy']);
            Route::post('/{supplier}/debts', [SupplierController::class, 'addDebt']);
            Route::post('/bulk-delete', [SupplierController::class, 'bulkDelete']);
            Route::post('/import', [SupplierController::class, 'import']);
        });
    });

    // ======================
    // SUPPLIER GROUPS
    // ======================
    Route::prefix('supplier-groups')->middleware(['permission:suppliers.view'])->group(function () {
        Route::get('/', [SupplierGroupController::class, 'index']);
        Route::get('/{group}', [SupplierGroupController::class, 'show']);
        Route::get('/export', [SupplierGroupController::class, 'export']);
        Route::get('/options/list', [SupplierGroupController::class, 'options']);
        Route::get('/stats/overview', [SupplierGroupController::class, 'statistics']);
        Route::get('/{group}/suppliers', [SupplierGroupController::class, 'suppliers']);

        Route::middleware(['permission:suppliers.manage'])->group(function () {
            Route::post('/', [SupplierGroupController::class, 'store']);
            Route::put('/{group}', [SupplierGroupController::class, 'update']);
            Route::delete('/{group}', [SupplierGroupController::class, 'destroy']);
        });
    });

    // ======================
    // CUSTOMERS
    // ======================
    Route::prefix('customers')->middleware(['permission:customers.view'])->group(function () {
        Route::get('/', [CustomerController::class, 'index']);
        Route::get('/{customer}', [CustomerController::class, 'show']);
        Route::get('/{customer}/edit', [CustomerController::class, 'edit']);
        Route::get('/create', [CustomerController::class, 'create']);
        Route::get('/export/csv', [CustomerController::class, 'export']);
        Route::get('/stats/overview', [CustomerController::class, 'statistics']);

        Route::middleware(['permission:customers.manage'])->group(function () {
            Route::post('/', [CustomerController::class, 'store']);
            Route::put('/{customer}', [CustomerController::class, 'update']);
            Route::patch('/{customer}', [CustomerController::class, 'update']);
            Route::delete('/{customer}', [CustomerController::class, 'destroy']);
            Route::post('/bulk-delete', [CustomerController::class, 'bulkDelete']);
            Route::post('/import', [CustomerController::class, 'import']);
        });
    });

    // ======================
    // CUSTOMER GROUPS
    // ======================
    Route::prefix('customer-groups')->middleware(['permission:customers.view'])->group(function () {
        Route::get('/', [CustomerGroupController::class, 'index']);
        Route::get('/{customerGroup}', [CustomerGroupController::class, 'show']);
        Route::get('/export', [CustomerGroupController::class, 'export']);
        Route::get('/options/list', [CustomerGroupController::class, 'options']);
        Route::get('/stats/overview', [CustomerGroupController::class, 'statistics']);
        Route::get('/{customerGroup}/customers', [CustomerGroupController::class, 'customers']);

        Route::middleware(['permission:customers.manage'])->group(function () {
            Route::post('/', [CustomerGroupController::class, 'store']);
            Route::put('/{customerGroup}', [CustomerGroupController::class, 'update']);
            Route::delete('/{customerGroup}', [CustomerGroupController::class, 'destroy']);
            Route::delete('/', [CustomerGroupController::class, 'bulkDelete']);
            Route::post('/import', [CustomerGroupController::class, 'import']);
        });
    });

    // ======================
    // CUSTOMER DEBTS
    // ======================
    Route::prefix('customer-debts')->middleware(['permission:customers.view'])->group(function () {
        Route::get('/', [CustomerDebtController::class, 'index']);
        Route::get('/{customerDebt}', [CustomerDebtController::class, 'show']);
        Route::get('/customer/summary', [CustomerDebtController::class, 'customerSummary']);
        Route::get('/customer/timeline', [CustomerDebtController::class, 'customerTimeline']);
        Route::get('/customers/with-debt', [CustomerDebtController::class, 'customersWithDebt']);
        Route::get('/reports/statistics', [CustomerDebtController::class, 'statistics']);
        Route::post('/export', [CustomerDebtController::class, 'export']);

        Route::middleware(['permission:customers.manage'])->group(function () {
            Route::post('/', [CustomerDebtController::class, 'store']);
            Route::put('/{customerDebt}', [CustomerDebtController::class, 'update']);
            Route::delete('/{customerDebt}', [CustomerDebtController::class, 'destroy']);
            Route::delete('/bulk/delete', [CustomerDebtController::class, 'bulkDelete']);
            Route::post('/payment', [CustomerDebtController::class, 'payment']);
            Route::post('/adjustment', [CustomerDebtController::class, 'adjustment']);
            Route::post('/import', [CustomerDebtController::class, 'import']);
        });
    });

    // ======================
    // WAREHOUSES
    // ======================
    Route::prefix('warehouses')->middleware(['permission:warehouse.view'])->group(function () {
        Route::get('/', [WarehouseController::class, 'index']);
        Route::get('/{id}', [WarehouseController::class, 'show']);
        Route::get('/{id}/products', [WarehouseController::class, 'products']);

        Route::middleware(['permission:warehouse.manage'])->group(function () {
            Route::post('/', [WarehouseController::class, 'store']);
            Route::put('/{id}', [WarehouseController::class, 'update']);
            Route::delete('/{id}', [WarehouseController::class, 'destroy']);
        });
    });

    // ======================
    // WAREHOUSE PRODUCTS
    // ======================
    Route::prefix('warehouse-products')->middleware(['permission:stock.view'])->group(function () {
    Route::get('/', [WarehouseProductController::class, 'index']);
    Route::get('/low-stock-alerts', [WarehouseProductController::class, 'lowStockAlerts']);
    Route::get('/out-of-stock', [WarehouseProductController::class, 'outOfStock']);
    Route::get('/stock-summary', [WarehouseProductController::class, 'stockSummary']);
    Route::get('/capacity-analysis/{warehouseId}', [WarehouseProductController::class, 'capacityAnalysis']);

    Route::middleware(['permission:stock.manage'])->group(function () {
        Route::put('/{id}', [WarehouseProductController::class, 'update']);
        Route::post('/adjust-stock', [WarehouseProductController::class, 'adjustStock']);
        Route::post('/transfer', [WarehouseProductController::class, 'transfer']);
        Route::post('/bulk-transfer', [WarehouseProductController::class, 'bulkTransfer']); // <-- THÊM DÒNG NÀY
    });
});

    //POS 
    Route::prefix('pos')->middleware(['permission:pos.use'])->group(function () {
        Route::get('/', [POSController::class, 'index']);
        Route::get('/products/search', [POSController::class, 'searchProducts']);
        Route::get('/customers/search', [POSController::class, 'searchCustomers']);
        Route::post('/orders', [POSController::class, 'createOrder']);
    });

    // ======================
// ORDERS
// ======================
Route::prefix('orders')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::get('/stats', [OrderController::class, 'stats']);
    Route::get('/export', [OrderController::class, 'export']);
    Route::get('/import-template', [OrderController::class, 'downloadTemplate']);
    Route::get('/{order}', [OrderController::class, 'show']);
    Route::put('/{order}', [OrderController::class, 'update']);

    Route::middleware(['permission:orders.manage'])->group(function () {
        Route::post('/', [OrderController::class, 'store']);
        Route::patch('/{order}/status', [OrderController::class, 'updateStatus']);
        Route::post('/{order}/payments', [OrderController::class, 'addPayment']);
        Route::delete('/{order}', [OrderController::class, 'destroy']);
        Route::post('/bulk-delete', [OrderController::class, 'bulkDelete']);
        Route::post('/import', [OrderController::class, 'import']);
        
        // QUY TRÌNH MỚI 5 BƯỚC - API endpoints
        Route::get('/{order}/next-action', [OrderController::class, 'getNextAction']);
        Route::post('/{order}/approve', [OrderController::class, 'approveOrder']);
        Route::post('/{order}/create-shipping', [OrderController::class, 'createShipping']);
        Route::post('/{order}/export-stock', [OrderController::class, 'exportStock']);
        Route::post('/{order}/complete-payment', [OrderController::class, 'completePayment']);
        Route::post('/{order}/cancel', [OrderController::class, 'cancelOrder']);
    });
});

// ======================
// ORDER RETURNS (Khách hàng trả hàng) - OUTSIDE ORDERS GROUP
// ======================
Route::prefix('order-returns')->middleware(['permission:orders.view'])->group(function () {
    Route::get('/', [OrderReturnController::class, 'index']);
    Route::get('/{orderReturn}', [OrderReturnController::class, 'show']);
    
    Route::middleware(['permission:orders.manage'])->group(function () {
        Route::post('/{orderReturn}/receive', [OrderReturnController::class, 'receive']);
        Route::post('/{orderReturn}/warehouse', [OrderReturnController::class, 'warehouse']);
        Route::post('/{orderReturn}/refund', [OrderReturnController::class, 'refund']);
        Route::post('/{orderReturn}/cancel', [OrderReturnController::class, 'cancel']);
    });
});

// Create return from order
Route::post('/orders/{order}/returns', [OrderReturnController::class, 'store'])
    ->middleware(['permission:orders.manage']);

// ======================
// SHIPPING
// ======================
Route::prefix('orders/{order}/shipping')->group(function () {
    Route::post('/', [App\Http\Controllers\Api\ShippingController::class, 'createShipping']);
    Route::get('/tracking', [App\Http\Controllers\Api\ShippingController::class, 'getTracking']);
    Route::patch('/{shipping}/status', [App\Http\Controllers\Api\ShippingController::class, 'updateStatus']);
});

// ======================
// SHIPPING PROVIDER & STATS - BỎ MIDDLEWARE
// ======================
Route::get('/shipping/providers', [App\Http\Controllers\Api\ShippingController::class, 'getProviders']);

Route::post('/orders/{orderId}/shipping/{shippingId}/confirm-delivery', [App\Http\Controllers\Api\ShippingController::class, 'confirmDelivery']);

Route::get('/shipping/stats', [App\Http\Controllers\Api\ShippingController::class, 'getStats']);

Route::get('/shippings', [App\Http\Controllers\Api\ShippingController::class, 'index']);

// ======================
// SHIPPING PROVIDERS MANAGEMENT
// ======================
Route::prefix('shipping-providers')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\ShippingProviderController::class, 'index']);
    Route::get('/stats', [App\Http\Controllers\Api\ShippingProviderController::class, 'getStats']);
    Route::get('/{id}', [App\Http\Controllers\Api\ShippingProviderController::class, 'show']);
    Route::post('/', [App\Http\Controllers\Api\ShippingProviderController::class, 'store']);
    Route::put('/{id}', [App\Http\Controllers\Api\ShippingProviderController::class, 'update']);
    Route::delete('/{id}', [App\Http\Controllers\Api\ShippingProviderController::class, 'destroy']);
    Route::post('/bulk-delete', [App\Http\Controllers\Api\ShippingProviderController::class, 'bulkDelete']);
    Route::patch('/{id}/toggle-status', [App\Http\Controllers\Api\ShippingProviderController::class, 'toggleStatus']);
});
    // PURCHASE

    Route::prefix('purchase-orders')->middleware(['permission:suppliers.view'])->group(function () {
    Route::get('/', [PurchaseOrderController::class, 'index']);
    Route::get('/statistics', [PurchaseOrderController::class, 'getStatistics']); // ← THÊM MỚI
    Route::get('/export', [PurchaseOrderController::class, 'export']); // ← THÊM MỚI
    Route::get('/payments/overview', [PurchaseOrderController::class, 'getPaymentOverview']);
    Route::get('/{id}', [PurchaseOrderController::class, 'show']);
    Route::get('/{id}/payments', [PurchaseOrderController::class, 'getPaymentHistory']);

    Route::middleware(['permission:suppliers.manage'])->group(function () {
        Route::post('/', [PurchaseOrderController::class, 'store']);
        Route::put('/{id}', [PurchaseOrderController::class, 'update']);
        Route::put('/{id}/status', [PurchaseOrderController::class, 'updateStatus']);
        Route::put('/{id}/submit-for-approval', [PurchaseOrderController::class, 'submitForApproval']);
    // Convert planned (order-only) order to actual
    Route::post('/{id}/convert-to-actual', [PurchaseOrderController::class, 'convertToActual']);
        Route::delete('/{id}', [PurchaseOrderController::class, 'destroy']);
        
        // PAYMENT ROUTES
        Route::post('/{id}/payments', [PurchaseOrderController::class, 'recordPayment']);
        Route::post('/payments/bulk', [PurchaseOrderController::class, 'bulkPayment']); // ← THÊM MỚI
        
    });
});

    // PURCHASE RECEIPTS
    // ======================
    Route::prefix('purchase-receipts')->middleware(['permission:suppliers.view'])->group(function () {
        Route::get('/', [PurchaseReceiptController::class, 'index']);
        Route::get('/{id}', [PurchaseReceiptController::class, 'show']);

        Route::middleware(['permission:suppliers.manage'])->group(function () {
            Route::post('/', [PurchaseReceiptController::class, 'store']);
            Route::patch('/{id}/approve', [PurchaseReceiptController::class, 'approve']);
            Route::put('/{id}', [PurchaseReceiptController::class, 'update']);
            Route::put('/{id}/cancel', [PurchaseReceiptController::class, 'cancel']);
            Route::delete('/{id}', [PurchaseReceiptController::class, 'destroy']);
        });
    });


    // PURCHASE RETURN ORDERS  
    // ======================
    Route::prefix('purchase-return-orders')->middleware(['permission:suppliers.view'])->group(function () {
        Route::get('/', [PurchaseReturnOrderController::class, 'index']);
        Route::get('/statistics', [PurchaseReturnOrderController::class, 'getStatistics']);
        Route::get('/export', [PurchaseReturnOrderController::class, 'export']);
        Route::get('/receipts/by-supplier/{supplierId}', [PurchaseReturnOrderController::class, 'getReceiptsBySupplier']);
        Route::get('/receipts/{receiptId}/returnable-items', [PurchaseReturnOrderController::class, 'getReturnableItems']);
        Route::get('/{id}', [PurchaseReturnOrderController::class, 'show']);

        Route::middleware(['permission:suppliers.manage'])->group(function () {
            Route::post('/', [PurchaseReturnOrderController::class, 'store']);
            Route::put('/{id}', [PurchaseReturnOrderController::class, 'update']);
            Route::put('/{id}/status', [PurchaseReturnOrderController::class, 'updateStatus']);
            Route::patch('/{id}/approve', [PurchaseReturnOrderController::class, 'approve']); // ← AUTO REDUCE DEBT
            Route::patch('/{id}/submit-for-approval', [PurchaseReturnOrderController::class, 'submitForApproval']);
            Route::patch('/{id}/cancel', [PurchaseReturnOrderController::class, 'cancel']);
            Route::delete('/{id}', [PurchaseReturnOrderController::class, 'destroy']);
            Route::post('/bulk-delete', [PurchaseReturnOrderController::class, 'bulkDelete']);
        });
    });

    // PURCHASE RETURN RECEIPTS
    // ======================
    Route::prefix('purchase-return-receipts')->middleware(['permission:suppliers.view'])->group(function () {
        Route::get('/', [PurchaseReturnReceiptController::class, 'index']);
        Route::get('/statistics', [PurchaseReturnReceiptController::class, 'getStatistics']);
        Route::get('/export', [PurchaseReturnReceiptController::class, 'export']);
        Route::get('/{id}', [PurchaseReturnReceiptController::class, 'show']);

        Route::middleware(['permission:suppliers.manage'])->group(function () {
            Route::post('/', [PurchaseReturnReceiptController::class, 'store']);
            Route::put('/{id}', [PurchaseReturnReceiptController::class, 'update']);
            Route::put('/{id}/status', [PurchaseReturnReceiptController::class, 'updateStatus']);
            Route::patch('/{id}/approve', [PurchaseReturnReceiptController::class, 'approve']); // ← AUTO REDUCE DEBT
            Route::patch('/{id}/submit-for-approval', [PurchaseReturnReceiptController::class, 'submitForApproval']);
            Route::patch('/{id}/cancel', [PurchaseReturnReceiptController::class, 'cancel']);
            Route::delete('/{id}', [PurchaseReturnReceiptController::class, 'destroy']);
            Route::post('/bulk-delete', [PurchaseReturnReceiptController::class, 'bulkDelete']);
        });
    });

    Route::prefix('cash-vouchers')->middleware(['permission:suppliers.view'])->group(function () {
        
        // CASH RECEIPT TYPES
        Route::prefix('receipt-types')->group(function () {
            Route::get('/', [CashReceiptTypeController::class, 'index']);
            Route::get('/{id}', [CashReceiptTypeController::class, 'show']);
            
            Route::middleware(['permission:suppliers.manage'])->group(function () {
                Route::post('/', [CashReceiptTypeController::class, 'store']);
                Route::put('/{id}', [CashReceiptTypeController::class, 'update']);
                Route::delete('/{id}', [CashReceiptTypeController::class, 'destroy']);
            });
        });

        // CASH PAYMENT TYPES
        Route::prefix('payment-types')->group(function () {
            Route::get('/', [CashPaymentTypeController::class, 'index']);
            Route::get('/{id}', [CashPaymentTypeController::class, 'show']);
            
            Route::middleware(['permission:suppliers.manage'])->group(function () {
                Route::post('/', [CashPaymentTypeController::class, 'store']);
                Route::put('/{id}', [CashPaymentTypeController::class, 'update']);
                Route::delete('/{id}', [CashPaymentTypeController::class, 'destroy']);
            });
        });

        // CASH RECEIPTS
        Route::prefix('receipts')->group(function () {
            Route::get('/', [CashReceiptController::class, 'index']);
            Route::get('/recipients', [CashReceiptController::class, 'getRecipients']);
            Route::get('/{id}', [CashReceiptController::class, 'show']);
            
            Route::middleware(['permission:suppliers.manage'])->group(function () {
                Route::post('/', [CashReceiptController::class, 'store']);
                Route::put('/{id}', [CashReceiptController::class, 'update']);
                Route::patch('/{id}/submit', [CashReceiptController::class, 'submitForApproval']);
                Route::patch('/{id}/approve', [CashReceiptController::class, 'approve']);
                Route::patch('/{id}/cancel', [CashReceiptController::class, 'cancel']);
            });
        });

        // CASH PAYMENTS
        Route::prefix('payments')->group(function () {
            Route::get('/', [CashPaymentController::class, 'index']);
            Route::get('/recipients', [CashPaymentController::class, 'getRecipients']);
            Route::get('/{id}', [CashPaymentController::class, 'show']);
            
            Route::middleware(['permission:suppliers.manage'])->group(function () {
                Route::post('/', [CashPaymentController::class, 'store']);
                Route::put('/{id}', [CashPaymentController::class, 'update']);
                Route::patch('/{id}/submit', [CashPaymentController::class, 'submitForApproval']);
                Route::patch('/{id}/approve', [CashPaymentController::class, 'approve']);
                Route::patch('/{id}/cancel', [CashPaymentController::class, 'cancel']);
            });
        });

        // CASH LEDGER
        Route::prefix('ledger')->group(function () {
            Route::get('/', [CashLedgerController::class, 'index']);
            Route::get('/summary', [CashLedgerController::class, 'summary']);
            Route::get('/export', [CashLedgerController::class, 'export']);
        });
    });
    // ======================
    // UTILITIES
    // ======================
    Route::get('/brands', function () {
        return response()->json([
            'success' => true,
            'data' => \App\Models\Product::select('brand_name')->distinct()->whereNotNull('brand_name')->pluck('brand_name')
        ]);
    });

    Route::get('/roles', function () {
        return response()->json([
            'success' => true,
            'data' => \App\Models\Role::select(['id', 'name', 'display_name', 'description'])->get()
        ]);
    });

    Route::get('/my-warehouses', function () {
        $user = auth()->user();
        $warehouseIds = $user->getAccessibleWarehouseIds();
        $warehouses = \App\Models\Warehouse::whereIn('id', $warehouseIds)
            ->where('status', 'active')
            ->select(['id', 'code', 'name', 'address'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $warehouses
        ]);
    });
});

// ==========================
// 🔚 Fallback nếu không khớp route
// ==========================
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API route not found'
    ], 404);
});