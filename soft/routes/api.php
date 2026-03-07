<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TwoFactorController;
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
use App\Http\Controllers\Api\SystemResetController;
use App\Http\Controllers\Api\ShippingProviderController;
use App\Http\Controllers\Api\CashReceiptTypeController;
use App\Http\Controllers\Api\CashPaymentTypeController;
use App\Http\Controllers\Api\CashReceiptController;
use App\Http\Controllers\Api\CashPaymentController;
use App\Http\Controllers\Api\CashLedgerController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EmployeeWorkScheduleController;
use App\Http\Controllers\Api\AttendanceDeviceController;
use App\Http\Controllers\Api\AttendanceLogController;
use App\Http\Controllers\Api\AttendanceAgentController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\PayrollSheetPaymentController;
use App\Http\Controllers\Api\EmployeeCommissionController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\EmployeeSetupController;
use App\Http\Controllers\Api\SalaryTemplateController;
use App\Http\Controllers\Api\EmployeeSalaryConfigController;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Controllers\Api\WorkdaySettingController;
use App\Http\Controllers\Api\TimekeepingRecordController;
use App\Http\Controllers\Api\TimekeepingSettingController;
use App\Http\Controllers\Api\PayrollSettingController;
use App\Http\Controllers\Api\PayrollSheetController;
use App\Http\Controllers\Api\PayrollSheetItemController;
use App\Http\Controllers\Api\EmployeeFinancialTransactionController;
use App\Http\Controllers\Api\ProductSerialController;

// Test POST route trên API (không có CSRF)
Route::post('/test-post', function(Request $request) {
    return response()->json([
        'success' => true,
        'message' => 'API POST works!',
        'input' => $request->all()
    ]);
});

// Test login qua API
Route::post('/test-login', function(Request $request) {
    \Log::info('API Login test', $request->all());
    
    return response()->json([
        'success' => true,
        'message' => 'API Login test works!',
        'credentials' => $request->only(['email', 'password'])
    ]);
});

// API login endpoint (no CSRF required)
Route::post('/login', [AuthController::class, 'apiLogin']);
Route::post('/2fa/verify', [TwoFactorController::class, 'verify']);


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// =======================
// 🔓 PUBLIC ROUTES
// =======================

// Debug: xem logs gần nhất (không cần auth, chỉ để test)
Route::get('/attendance-agent/recent-logs', function () {
    $logs = \App\Models\AttendanceLog::query()
        ->orderByDesc('created_at')
        ->limit(20)
        ->get(['id', 'device_user_id', 'punched_at', 'event_type', 'employee_id', 'created_at']);
    
    return response()->json([
        'success' => true,
        'total_logs' => \App\Models\AttendanceLog::count(),
        'recent' => $logs,
    ]);
});

// Debug: xem trạng thái attendance và recalculate ngay (sync mode)
Route::get('/attendance-agent/debug-status', function () {
    $today = now()->toDateString();
    $from = now()->subDays(7)->toDateString();
    
    // Đếm logs, schedules, timekeeping records
    $totalLogs = \App\Models\AttendanceLog::count();
    $logsWithEmployee = \App\Models\AttendanceLog::whereNotNull('employee_id')->count();
    $logsWithoutEmployee = \App\Models\AttendanceLog::whereNull('employee_id')->count();
    
    $schedulesThisWeek = \App\Models\EmployeeWorkSchedule::whereBetween('work_date', [$from, $today])->count();
    $timekeepingRecordsThisWeek = \App\Models\TimekeepingRecord::whereBetween('work_date', [$from, $today])->count();
    $timekeepingWithCheckin = \App\Models\TimekeepingRecord::whereBetween('work_date', [$from, $today])
        ->whereNotNull('check_in_at')
        ->count();
    
    // List employees với attendance_code
    $employeesWithCode = \App\Models\Employee::whereNotNull('attendance_code')
        ->get(['id', 'code', 'name', 'attendance_code']);
    
    // Recent schedules
    $recentSchedules = \App\Models\EmployeeWorkSchedule::with(['employee:id,code,name', 'timekeepingRecord'])
        ->whereBetween('work_date', [$from, $today])
        ->orderByDesc('work_date')
        ->limit(10)
        ->get();
    
    return response()->json([
        'success' => true,
        'today' => $today,
        'summary' => [
            'total_logs' => $totalLogs,
            'logs_with_employee_id' => $logsWithEmployee,
            'logs_without_employee_id' => $logsWithoutEmployee,
            'schedules_this_week' => $schedulesThisWeek,
            'timekeeping_records_this_week' => $timekeepingRecordsThisWeek,
            'timekeeping_with_checkin' => $timekeepingWithCheckin,
        ],
        'employees_with_attendance_code' => $employeesWithCode,
        'recent_schedules' => $recentSchedules,
    ]);
});

// Debug: Force recalculate timekeeping ngay lập tức (sync mode, không qua queue)
Route::post('/attendance-agent/force-recalculate', function (\Illuminate\Http\Request $request) {
    $from = $request->input('from', now()->subDays(7)->toDateString());
    $to = $request->input('to', now()->toDateString());
    $employeeId = $request->input('employee_id');
    
    $service = app(\App\Services\TimekeepingService::class);
    $result = $service->recalculateForRange(
        \Carbon\Carbon::parse($from),
        \Carbon\Carbon::parse($to),
        $employeeId ? (int) $employeeId : null
    );
    
    return response()->json([
        'success' => true,
        'message' => 'Đã recalculate timekeeping (sync mode)',
        'range' => ['from' => $from, 'to' => $to],
        'employee_id' => $employeeId,
        'result' => $result,
    ]);
});

// LAN Attendance Agent (signed)
Route::prefix('attendance-agent')->middleware('attendance.agent')->group(function () {
    Route::post('/push-logs', [AttendanceAgentController::class, 'pushLogs']);
    Route::get('/users', [AttendanceAgentController::class, 'getUsers']);
    Route::post('/sync-status', [AttendanceAgentController::class, 'syncStatus']);
    Route::get('/bridge/latest', [AttendanceAgentController::class, 'getLatestVersion']);
    Route::post('/refresh-mapping', [AttendanceAgentController::class, 'refreshMapping']);
});

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
    // PRODUCT SERIALS
    // ======================
    Route::prefix('product-serials')->middleware(['permission:products.view'])->group(function () {
        Route::get('/', [ProductSerialController::class, 'index']);
        Route::get('/available', [ProductSerialController::class, 'available']);
        Route::get('/lookup', [ProductSerialController::class, 'lookup']);
        Route::get('/{id}', [ProductSerialController::class, 'show']);
        Route::get('/{id}/history', [ProductSerialController::class, 'history']);

        Route::middleware(['permission:products.manage'])->group(function () {
            Route::post('/bulk-import', [ProductSerialController::class, 'bulkImport']);
            Route::put('/{id}', [ProductSerialController::class, 'update']);
            Route::delete('/{id}', [ProductSerialController::class, 'destroy']);
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
    // EMPLOYEES / STAFF
    // ======================
    Route::prefix('employees')->middleware(['permission:staff.view'])->group(function () {
        Route::get('/', [EmployeeController::class, 'index']);
        Route::get('/{employee}', [EmployeeController::class, 'show']);

        Route::middleware(['permission:staff.manage'])->group(function () {
            Route::post('/', [EmployeeController::class, 'store']);
            Route::put('/{employee}', [EmployeeController::class, 'update']);
            Route::delete('/{employee}', [EmployeeController::class, 'destroy']);
            Route::post('/{employee}/avatar', [EmployeeController::class, 'uploadAvatar']);
        });
    });

    Route::prefix('employee-financial-transactions')->middleware(['permission:staff.view'])->group(function () {
        Route::get('/', [EmployeeFinancialTransactionController::class, 'index']);

        Route::middleware(['permission:staff.manage'])->group(function () {
            Route::post('/', [EmployeeFinancialTransactionController::class, 'store']);
            Route::put('/{employeeFinancialTransaction}', [EmployeeFinancialTransactionController::class, 'update']);
            Route::delete('/{employeeFinancialTransaction}', [EmployeeFinancialTransactionController::class, 'destroy']);
        });
    });

    Route::prefix('employee-schedules')->middleware(['permission:staff.view'])->group(function () {
        Route::get('/', [EmployeeWorkScheduleController::class, 'index']);

        Route::middleware(['permission:staff.manage'])->group(function () {
            Route::post('/', [EmployeeWorkScheduleController::class, 'store']);
            Route::put('/{schedule}', [EmployeeWorkScheduleController::class, 'update']);
            Route::delete('/{schedule}', [EmployeeWorkScheduleController::class, 'destroy']);
        });
    });

    Route::prefix('employee-setup')->middleware(['permission:staff.view'])->group(function () {
        Route::get('/overview', [EmployeeSetupController::class, 'overview']);
    });

    Route::prefix('shifts')->middleware(['permission:staff.view'])->group(function () {
        Route::get('/', [ShiftController::class, 'index']);
        Route::get('/{shift}', [ShiftController::class, 'show']);

        Route::middleware(['permission:staff.manage'])->group(function () {
            Route::post('/', [ShiftController::class, 'store']);
            Route::put('/{shift}', [ShiftController::class, 'update']);
            Route::patch('/{shift}/toggle', [ShiftController::class, 'toggle']);
            Route::delete('/{shift}', [ShiftController::class, 'destroy']);
        });
    });

    Route::prefix('salary-templates')->middleware(['permission:staff.view'])->group(function () {
        Route::get('/', [SalaryTemplateController::class, 'index']);
        Route::get('/{salaryTemplate}', [SalaryTemplateController::class, 'show']);

        Route::middleware(['permission:staff.manage'])->group(function () {
            Route::post('/', [SalaryTemplateController::class, 'store']);
            Route::put('/{salaryTemplate}', [SalaryTemplateController::class, 'update']);
            Route::delete('/{salaryTemplate}', [SalaryTemplateController::class, 'destroy']);
        });
    });

    Route::prefix('employee-salary-configs')->middleware(['permission:staff.view'])->group(function () {
        Route::get('/', [EmployeeSalaryConfigController::class, 'index']);
        Route::get('/{employeeSalaryConfig}', [EmployeeSalaryConfigController::class, 'show']);

        Route::middleware(['permission:staff.manage'])->group(function () {
            Route::post('/', [EmployeeSalaryConfigController::class, 'store']);
            Route::put('/{employeeSalaryConfig}', [EmployeeSalaryConfigController::class, 'update']);
            Route::delete('/{employeeSalaryConfig}', [EmployeeSalaryConfigController::class, 'destroy']);
        });
    });

    Route::prefix('holidays')->middleware(['permission:staff.view'])->group(function () {
        Route::get('/', [HolidayController::class, 'index']);
        Route::get('/{holiday}', [HolidayController::class, 'show']);

        Route::middleware(['permission:staff.manage'])->group(function () {
            Route::post('/', [HolidayController::class, 'store']);
            Route::post('/auto-generate', [HolidayController::class, 'autoGenerate']);
            Route::put('/{holiday}', [HolidayController::class, 'update']);
            Route::delete('/{holiday}', [HolidayController::class, 'destroy']);
        });
    });

    Route::prefix('workday-settings')->middleware(['permission:staff.view'])->group(function () {
        Route::get('/', [WorkdaySettingController::class, 'show']);

        Route::middleware(['permission:staff.manage'])->group(function () {
            Route::post('/', [WorkdaySettingController::class, 'upsert']);
        });
    });

    Route::prefix('timekeeping-settings')->middleware(['permission:staff.view'])->group(function () {
        Route::get('/', [TimekeepingSettingController::class, 'show']);

        Route::middleware(['permission:staff.manage'])->group(function () {
            Route::post('/', [TimekeepingSettingController::class, 'upsert']);
        });
    });

    Route::prefix('payroll-settings')->middleware(['permission:staff.view'])->group(function () {
        Route::get('/', [PayrollSettingController::class, 'show']);

        Route::middleware(['permission:staff.manage'])->group(function () {
            Route::post('/', [PayrollSettingController::class, 'upsert']);
        });
    });

    Route::prefix('timekeeping-records')->middleware(['permission:staff.view'])->group(function () {
        Route::get('/', [TimekeepingRecordController::class, 'index']);
        Route::get('/{timekeepingRecord}', [TimekeepingRecordController::class, 'show']);

        Route::middleware(['permission:staff.manage'])->group(function () {
            Route::post('/', [TimekeepingRecordController::class, 'store']);
            Route::put('/{timekeepingRecord}', [TimekeepingRecordController::class, 'update']);
            Route::post('/recalculate', [TimekeepingRecordController::class, 'recalculate']);
        });
    });

    Route::prefix('payroll-sheets')->middleware(['permission:staff.view'])->group(function () {
        Route::get('/', [PayrollSheetController::class, 'index']);
        Route::get('/{payrollSheet}', [PayrollSheetController::class, 'show']);

        Route::middleware(['permission:staff.manage'])->group(function () {
            Route::post('/generate', [PayrollSheetController::class, 'generate']);
            Route::post('/{payrollSheet}/lock', [PayrollSheetController::class, 'lock']);
            Route::post('/{payrollSheet}/mark-paid', [PayrollSheetController::class, 'markPaid']);
        });
    });

    Route::prefix('payroll-sheet-items')->middleware(['permission:staff.view'])->group(function () {
        Route::get('/', [PayrollSheetItemController::class, 'index']);
    });

    Route::prefix('payroll-sheet-payments')->middleware(['permission:staff.view'])->group(function () {
        Route::get('/', [PayrollSheetPaymentController::class, 'index']);

        Route::middleware(['permission:staff.manage'])->group(function () {
            Route::post('/', [PayrollSheetPaymentController::class, 'store']);
        });
    });

    Route::prefix('attendance-devices')->middleware(['permission:staff.view'])->group(function () {
        Route::get('/', [AttendanceDeviceController::class, 'index']);
        Route::get('/{device}', [AttendanceDeviceController::class, 'show']);
        Route::post('/{device}/test-connection', [AttendanceDeviceController::class, 'testConnection']);
        Route::post('/{device}/sync', [AttendanceDeviceController::class, 'sync']);

        Route::middleware(['permission:staff.manage'])->group(function () {
            Route::post('/', [AttendanceDeviceController::class, 'store']);
            Route::put('/{device}', [AttendanceDeviceController::class, 'update']);
            Route::delete('/{device}', [AttendanceDeviceController::class, 'destroy']);
        });
    });

    Route::prefix('attendance-logs')->middleware(['permission:staff.view'])->group(function () {
        Route::get('/', [AttendanceLogController::class, 'index']);
        Route::get('/unmapped-users', [AttendanceLogController::class, 'unmappedUsers']);
        
        Route::middleware(['permission:staff.manage'])->group(function () {
            Route::post('/refresh-mapping', [AttendanceLogController::class, 'refreshMapping']);
        });
    });

    Route::prefix('payrolls')->middleware(['permission:staff.view'])->group(function () {
        Route::get('/', [PayrollController::class, 'index']);
        Route::get('/{payroll}', [PayrollController::class, 'show']);

        Route::middleware(['permission:staff.manage'])->group(function () {
            Route::post('/', [PayrollController::class, 'store']);
            Route::put('/{payroll}', [PayrollController::class, 'update']);
            Route::delete('/{payroll}', [PayrollController::class, 'destroy']);
        });
    });

    Route::prefix('commissions')->middleware(['permission:staff.view'])->group(function () {
        Route::get('/', [EmployeeCommissionController::class, 'index']);
        Route::get('/{commission}', [EmployeeCommissionController::class, 'show']);

        Route::middleware(['permission:staff.manage'])->group(function () {
            Route::post('/', [EmployeeCommissionController::class, 'store']);
            Route::put('/{commission}', [EmployeeCommissionController::class, 'update']);
            Route::delete('/{commission}', [EmployeeCommissionController::class, 'destroy']);
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
    Route::get('/filter-data', [OrderController::class, 'filterData']);
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
// � SYSTEM RESET (chỉ super_admin)
// ==========================
Route::prefix('system')->middleware(['auth:sanctum', 'role:super_admin,admin'])->group(function () {
    Route::post('/reset-all-data', [SystemResetController::class, 'resetAllData']);
});

// ==========================
// �🔚 Fallback nếu không khớp route
// ==========================
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API route not found'
    ], 404);
});
