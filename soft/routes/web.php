<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\POSController;
use App\Http\Controllers\PurchaseReturnReceiptController;
use App\Http\Controllers\PurchaseReturnOrderController;
use App\Http\Controllers\Api\CashReceiptController;
use App\Http\Controllers\Api\CashPaymentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


// Test route bypass middleware hoàn toàn
Route::post('/test-bypass', function(Request $request) {
    return response()->json([
        'success' => true,
        'message' => 'Bypass route works!',
        'input' => $request->all()
    ]);
});



/*
|--------------------------------------------------------------------------
| Web Routes - Hybrid Auth System
|--------------------------------------------------------------------------
*/

// ===== GUEST ROUTES =====
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// Forced logout endpoint (GET) to avoid SPA redirect loops.
Route::get('/force-logout', [AuthController::class, 'forceLogout'])->name('force-logout');

// 2FA routes (không có middleware guest vì user đã logout)
Route::get('/2fa/challenge', [AuthController::class, 'show2FAChallenge'])->name('2fa.challenge');
Route::post('/2fa/verify', [AuthController::class, 'verify2FA'])->name('2fa.verify');

// Test routes for debugging
Route::get('/test-session', function() {
    $sessionData = [
        'session_id' => session()->getId(),
        'all_data' => session()->all(),
        'has_2fa_user_id' => session()->has('2fa_user_id'),
        '2fa_user_id' => session('2fa_user_id'),
    ];
    
    return response()->json($sessionData);
});

Route::post('/test-login-simple', function(Request $request) {
    \Log::info('Test login called', [
        'email' => $request->email,
        'all_input' => $request->all()
    ]);
    
    return response()->json([
        'success' => true,
        'message' => 'Test route works',
        'input' => $request->all()
    ]);
});

Route::get('/test-2fa-manual', function() {
    session()->put('2fa_user_id', 1);
    session()->put('2fa_remember', false);
    session()->save(); // Force save
    
    return redirect()->route('2fa.challenge');
});

// Debug route (accessible to all)
Route::get('/auth-debug', function () {
    return view('auth-debug');
});

Route::get('/debug-2fa', function () {
    return view('debug-2fa');
});

Route::get('/meta-test', function () {
    return view('meta-test');
});

// ===== AUTHENTICATED ROUTES =====
Route::middleware(['auth:web', 'active.user'])->group(function () {
    // Logout (POST only to avoid CSRF via GET)
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    
    // Root redirect
    Route::get('/', function () {
        return redirect('/dashboard');
    });
    
    // Dashboard - áp dụng auto.warehouse cho non-admin users
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard')->middleware('auto.warehouse');
    
    // Products Management - cần warehouse context
    //Route::middleware(['permission:products.view', 'auto.warehouse'])->group(function () {
        Route::get('/products', function () {
            return view('products.index');
        })->name('products.index');
        
        Route::get('/categories', function () {
            return view('categories.index');
        })->name('categories.index');
        
        Route::get('/units', function () {
            return view('units.index');
        })->name('units.index');
    //});
    
    // Suppliers Management - không cần warehouse context
    Route::middleware(['permission:suppliers.view'])->group(function () {
        Route::get('/suppliers', function () {
            return view('suppliers.index');
        })->name('suppliers.index');
    });
    
    // Customers Management - cần warehouse context
    Route::middleware(['permission:customers.view', 'auto.warehouse'])->group(function () {
        Route::get('/customers', function () {
            return view('customers.index');
        })->name('customers.index');
        
        Route::get('/customer-groups', function () {
            return view('customer-groups.index');
        })->name('customer-groups.index');
        
        Route::get('/customer-debts', function () {
            return view('customer-debts.index');
        })->name('customer-debts.index');
    });

    // Orders Management - cần warehouse context
    // Orders Management - cần warehouse context
Route::middleware(['permission:orders.view', 'auto.warehouse'])->group(function () {
    Route::get('/orders', function () {
        return view('orders.index');
    })->name('orders.index');
    
    // ĐẶT CÁC ROUTE ĐẶC BIỆT TRƯỚC ROUTE ĐỘNG
    Route::get('/orders/shipping', function () {
        return view('orders.shipping');
    })->name('orders.shipping');
    
    Route::get('/orders/payment', function () {
        return view('orders.payment');
    })->name('orders.payment');
    
    Route::get('/orders/create', function() {
        return view('orders.create');
    })->name('orders.create');

    // AJAX routes for order creation
    Route::get('/search/customers', [\App\Http\Controllers\Api\CustomerController::class, 'index'])->name('search.customers');
    Route::get('/search/customers/{customer}', [\App\Http\Controllers\Api\CustomerController::class, 'show'])->name('search.customers.show');
    Route::get('/search/products', [\App\Http\Controllers\Api\ProductController::class, 'index'])->name('search.products');
    Route::post('/orders/store', [\App\Http\Controllers\Api\OrderController::class, 'store'])->name('orders.store');
    Route::post('/orders/{order}/approve', [\App\Http\Controllers\Api\OrderController::class, 'approveOrder'])->name('orders.approve');

    // CÁC ROUTE ĐỘNG ĐẶT CUỐI CÙNG
    Route::get('/orders/{id}/edit', function($id) {
        return view('orders.edit', ['orderId' => $id]);
    })->name('orders.edit');

    Route::get('/orders/{id}', function($id) {
        return view('orders.show', ['orderId' => $id]);
    })->name('orders.show');
    
});

// Order Returns Management - cần warehouse context  
Route::middleware(['auto.warehouse'])->group(function () {
    Route::get('/order-returns', function () {
        return view('order-returns');
    })->name('order-returns.index');

    Route::get('/order-returns/{id}', function($id) {
        return view('order-returns.show', ['returnId' => $id]);
    })->name('order-returns.show');
});

    // Shipping Management - cần warehouse context
    Route::middleware(['permission:orders.view', 'auto.warehouse'])->group(function () {
        Route::get('/shipping', function () {
            return view('shipping.index');
        })->name('shipping.index');
    });

    // Shipping Providers Management - chỉ admin mới truy cập
    Route::middleware(['permission:warehouse.manage'])->group(function () {
        Route::get('/shipping/providers', function () {
            return view('shipping.providers');
        })->name('shipping.providers');
    });
    // POS System - cần warehouse context
    Route::middleware(['permission:pos.use', 'auto.warehouse'])->group(function () {
        Route::get('/pos', [POSController::class, 'index'])
            ->name('pos.index')
            ->defaults('layout', 'pos'); // Sử dụng layout riêng
            
        Route::get('/pos/{id}/print', [POSController::class, 'showPrint'])->name('pos.print');
    });

    // Purchase Orders Management - không cần warehouse context (quản lý tổng)
    Route::middleware(['permission:suppliers.view'])->group(function () {
        Route::get('/purchase-orders', function () {
            return view('purchase-orders.index');
        })->name('purchase-orders.index');

        Route::get('/purchase-orders/create', function () {
            return view('purchase-orders.create');
        })->name('purchase-orders.create');

        Route::get('/purchase-orders/{id}', function ($id) {
            return view('purchase-orders.show', compact('id'));
        })->name('purchase-orders.show');

        Route::get('/purchase-orders/{id}/edit', function ($id) {
            return view('purchase-orders.edit', compact('id'));
        })->name('purchase-orders.edit');
    });

    // Purchase Receipts Management
    Route::middleware(['permission:suppliers.view'])->group(function () {
        Route::get('/purchase-receipts', function () {
            return view('purchase-receipts.index');
        })->name('purchase-receipts.index');

        Route::get('/purchase-receipts/create', function () {
            return view('purchase-receipts.create');
        })->name('purchase-receipts.create');

        Route::get('/purchase-receipts/{id}', function ($id) {
            return view('purchase-receipts.show', compact('id'));
        })->name('purchase-receipts.show');
        // Edit view (needed for action buttons in list & detail pages)
        Route::get('/purchase-receipts/{id}/edit', function ($id) {
            return view('purchase-receipts.edit', compact('id'));
        })->name('purchase-receipts.edit');
        // Print view
        Route::get('/purchase-receipts/{id}/print', [App\Http\Controllers\Api\PurchaseReceiptController::class, 'print'])
            ->name('purchase-receipts.print');
    });

    // Purchase Return Orders Management
    Route::middleware(['permission:suppliers.view'])->group(function () {
        Route::get('/purchase-return-orders', function () {
            return view('purchase-return-orders.index');
        })->name('purchase-return-orders.index');

        Route::get('/purchase-return-orders/create', function () {
            return view('purchase-return-orders.create');
        })->name('purchase-return-orders.create');

        Route::get('/purchase-return-orders/{id}', function ($id) {
            return view('purchase-return-orders.show', compact('id'));
        })->name('purchase-return-orders.show');

        Route::get('/purchase-return-orders/{id}/edit', function ($id) {
            return view('purchase-return-orders.edit', compact('id'));
        })->name('purchase-return-orders.edit');
        Route::get('/purchase-return-orders/{id}/print', [PurchaseReturnOrderController::class, 'print'])->name('purchase-return-orders.print');
    });

    // Purchase Return Receipts Management
    Route::middleware(['permission:suppliers.view'])->group(function () {
        Route::get('/purchase-return-receipts', function () {
            return view('purchase-return-receipts.index');
        })->name('purchase-return-receipts.index');

        Route::get('/purchase-return-receipts/create', function () {
            return view('purchase-return-receipts.create');
        })->name('purchase-return-receipts.create');

        Route::get('/purchase-return-receipts/{id}', function ($id) {
            return view('purchase-return-receipts.show', compact('id'));
        })->name('purchase-return-receipts.show');
        Route::get('/purchase-return-receipts/{id}/print', [PurchaseReturnReceiptController::class, 'print'])->name('purchase-return-receipts.print');
    });
    Route::middleware(['permission:suppliers.view'])->group(function () {
        Route::get('/cash-receipts', function () {
            return view('cash-receipts.index');
        })->name('cash-receipts.index');

        Route::get('/cash-payments', function () {
            return view('cash-payments.index');
        })->name('cash-payments.index');

        Route::get('/cash-ledger', function () {
            return view('cash-ledger.index');
        })->name('cash-ledger.index');

        Route::get('/cash-receipts/{id}/print', [App\Http\Controllers\Api\CashReceiptController::class, 'print'])
    ->name('cash-receipts.print');

        Route::get('/cash-payments/{id}/print', [
        App\Http\Controllers\Api\CashPaymentController::class, 
        'print'
    ])->name('cash-payments.print');
    });

    // Employees / Staff Management
    Route::middleware(['permission:staff.view'])->group(function () {
        Route::get('/employees', function () {
            return view('employees.index');
        })->name('employees.index');

        Route::get('/employees/schedules', function () {
            return view('employees.schedules');
        })->name('employees.schedules');

        Route::get('/employees/attendance', function () {
            return view('employees.attendance');
        })->name('employees.attendance');

        // View raw unmapped device_user_id codes (logs exist but not mapped to employees)
        Route::get('/employees/attendance/unmapped-users', function () {
            $rows = \App\Models\AttendanceLog::query()
                ->whereNull('employee_id')
                ->where('device_user_id', '<>', '0')
                ->select('device_user_id', DB::raw('COUNT(*) as log_count'), DB::raw('MAX(punched_at) as last_punch'))
                ->groupBy('device_user_id')
                ->orderByDesc('log_count')
                ->limit(1000)
                ->get();

            return view('employees.attendance-unmapped', compact('rows'));
        })->name('employees.attendance.unmapped-users');

        // Trigger refresh mapping from web (no need for Bearer token). Requires staff.manage.
        Route::post('/employees/attendance/unmapped-users/refresh-mapping', function (\Illuminate\Http\Request $request) {
            $request->validate([
                'device_id' => ['nullable', 'integer'],
                'device_user_ids' => ['nullable', 'string'],
            ]);

            $deviceId = $request->integer('device_id') ?: null;
            $rawIds = trim((string) $request->input('device_user_ids', ''));
            $deviceUserIds = [];
            if ($rawIds !== '') {
                $deviceUserIds = array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', $rawIds) ?: [])));
            }

            $payload = [];
            if ($deviceId) {
                $payload['device_id'] = $deviceId;
            }
            if (!empty($deviceUserIds)) {
                $payload['device_user_ids'] = $deviceUserIds;
            }

            $controller = app(\App\Http\Controllers\Api\AttendanceLogController::class);
            $apiRequest = \Illuminate\Http\Request::create('/api/attendance-logs/refresh-mapping', 'POST', $payload);
            $apiRequest->setUserResolver(fn () => auth()->user());

            $resp = $controller->refreshMapping($apiRequest);
            $data = method_exists($resp, 'getData') ? $resp->getData(true) : [];

            if (($data['success'] ?? false) === true) {
                $msg = $data['message'] ?? 'Đã refresh mapping';
                return redirect()->route('employees.attendance.unmapped-users')->with('success', $msg);
            }

            $msg = $data['message'] ?? 'Refresh mapping thất bại';
            return redirect()->route('employees.attendance.unmapped-users')->with('error', $msg);
        })->middleware(['permission:staff.manage'])->name('employees.attendance.unmapped-users.refresh-mapping');

        Route::get('/employees/payroll', function () {
            return view('employees.payroll');
        })->name('employees.payroll');

        Route::get('/employees/commissions', function () {
            return view('employees.commissions');
        })->name('employees.commissions');

        Route::get('/employees/settings', function () {
            return view('employees.settings');
        })->name('employees.settings');

        Route::get('/employees/settings/devices', function () {
            return view('employees.attendance-devices');
        })->name('employees.settings.devices');

        // IMPORTANT: đặt route động sau cùng để không đè các route tĩnh
        Route::get('/employees/{id}', function ($id) {
            return view('employees.show', ['employeeId' => $id]);
        })->name('employees.show');
    });
    
    // Warehouses Management - chỉ admin mới truy cập
    Route::middleware(['permission:warehouse.view'])->group(function () {
        Route::get('/warehouses', function () {
            return view('warehouse.warehouses');
        })->name('warehouses.index');
    });
    
    // Admin Routes - không cần warehouse context
    Route::middleware(['role:super_admin,admin'])->group(function () {
        Route::get('/admin/users', function () {
            return view('admin.users');
        })->name('admin.users');
        
        Route::get('/admin/roles', function () {
            return view('admin.roles');
        })->name('admin.roles');
        
        Route::get('/admin/settings', function () {
            return view('admin.settings');
        })->name('admin.settings');
    });
});


// ===== FALLBACK =====
Route::fallback(function () {
    return redirect('/login');
});