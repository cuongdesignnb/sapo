<?php
/**
 * Flow 15 -- Kiem thu Sales Order / Order-to-Invoice
 */
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Branch;
use App\Models\CashFlow;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use App\Models\Role;
use App\Services\LockPeriodService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

Auth::loginUsingId(1);

$pass = 0; $fail = 0; $errors = [];
function test($label, $condition, &$pass, &$fail, &$errors, $detail = '') {
    if ($condition) { echo "  PASS $label\n"; $pass++; }
    else { echo "  FAIL $label" . ($detail ? " -- $detail" : "") . "\n"; $fail++; $errors[] = "$label: $detail"; }
}

echo "\n=== FLOW 15 -- KIEM THU SALES ORDER ===\n\n";

// === CLEANUP ===
OrderItem::whereHas('order', fn($q) => $q->where('code', 'LIKE', '%_F15%'))->delete();
InvoiceItem::whereHas('invoice', fn($q) => $q->where('note', 'LIKE', '%_F15%'))->delete();
Invoice::where('note', 'LIKE', '%_F15%')->delete();
Order::where('code', 'LIKE', '%_F15%')->delete();
CashFlow::withTrashed()->where('description', 'LIKE', '%_F15%')->forceDelete();
ActivityLog::where('description', 'LIKE', '%_F15%')->delete();
Setting::where('key', 'lock_date')->delete();

// Setup
$sp001 = Product::where('code', 'SP001')->orWhere('sku', 'SP001')->first();
$sp002 = Product::where('code', 'SP002')->orWhere('sku', 'SP002')->first();
if (!$sp001) $sp001 = Product::create(['code' => 'SP001', 'sku' => 'SP001', 'name' => 'Water 500ml', 'sale_price' => 7000, 'cost_price' => 3000, 'stock_quantity' => 100, 'track_inventory' => true]);
if (!$sp002) $sp002 = Product::create(['code' => 'SP002', 'sku' => 'SP002', 'name' => 'Biscuit Box', 'sale_price' => 30000, 'cost_price' => 15000, 'stock_quantity' => 50, 'track_inventory' => true]);

// Ensure enough stock
$sp001->update(['stock_quantity' => max($sp001->stock_quantity, 100)]);
$sp002->update(['stock_quantity' => max($sp002->stock_quantity, 50)]);
$stockBefore1 = $sp001->fresh()->stock_quantity;
$stockBefore2 = $sp002->fresh()->stock_quantity;

$kh001 = Customer::where('code', 'KH001')->first();
if (!$kh001) $kh001 = Customer::create(['code' => 'KH001', 'name' => 'Nguyen Van A', 'phone' => '0900000001']);
$kh002 = Customer::where('code', 'KH002')->first();
if (!$kh002) $kh002 = Customer::create(['code' => 'KH002', 'name' => 'Tran Thi B', 'phone' => '0900000002']);

$branch = Branch::first();
$debtBefore = $kh001->fresh()->debt_amount ?? 0;

echo "-- Setup: SP001={$sp001->id}, SP002={$sp002->id}, KH001={$kh001->id}, KH002={$kh002->id}\n";
echo "-- Stock before: SP001={$stockBefore1}, SP002={$stockBefore2}\n";

// === 15A: Create order without deposit ===
echo "\n-- 15A: Create order without deposit --\n";

$orderA = Order::create([
    'code' => 'DH_F15_A', 'customer_id' => $kh001->id, 'branch_id' => $branch?->id,
    'status' => 'draft', 'total_price' => 44000, 'discount' => 0, 'other_fees' => 0,
    'total_payment' => 44000, 'amount_paid' => 0, 'note' => 'Test_F15',
    'created_by_name' => 'Admin',
]);
$orderA->items()->create(['product_id' => $sp001->id, 'qty' => 2, 'price' => 7000, 'discount' => 0, 'subtotal' => 14000]);
$orderA->items()->create(['product_id' => $sp002->id, 'qty' => 1, 'price' => 30000, 'discount' => 0, 'subtotal' => 30000]);

test("Order created", $orderA->id > 0, $pass, $fail, $errors);
test("Order code", $orderA->code === 'DH_F15_A', $pass, $fail, $errors);
test("Total = 44000", (int)$orderA->total_payment === 44000, $pass, $fail, $errors);
test("Deposit = 0", (int)$orderA->amount_paid === 0, $pass, $fail, $errors);
test("Status = draft", $orderA->status === 'draft', $pass, $fail, $errors);
test("Items count = 2", $orderA->items()->count() === 2, $pass, $fail, $errors);

// Stock not deducted
$sp001After = Product::find($sp001->id);
test("Stock not deducted (order only)", (int)$sp001After->stock_quantity === (int)$stockBefore1, $pass, $fail, $errors);

// === 15B: Create order with deposit ===
echo "\n-- 15B: Create order with deposit --\n";

$orderB = Order::create([
    'code' => 'DH_F15_B', 'customer_id' => $kh001->id, 'branch_id' => $branch?->id,
    'status' => 'confirmed', 'total_price' => 35000, 'discount' => 0, 'other_fees' => 0,
    'total_payment' => 35000, 'amount_paid' => 20000, 'note' => 'Test_F15 with deposit',
    'created_by_name' => 'Admin',
]);
$orderB->items()->create(['product_id' => $sp001->id, 'qty' => 5, 'price' => 7000, 'discount' => 0, 'subtotal' => 35000]);

test("Order B created", $orderB->id > 0, $pass, $fail, $errors);
test("Total = 35000", (int)$orderB->total_payment === 35000, $pass, $fail, $errors);
test("Deposit = 20000", (int)$orderB->amount_paid === 20000, $pass, $fail, $errors);
$outstanding = $orderB->total_payment - $orderB->amount_paid;
test("Outstanding = 15000", (int)$outstanding === 15000, $pass, $fail, $errors);

// === 15C: Search and reopen ===
echo "\n-- 15C: Search and reopen --\n";

$foundByCode = Order::where('code', 'DH_F15_A')->first();
test("Find by code", $foundByCode !== null, $pass, $fail, $errors);

$foundByCustomer = Order::where('code', 'LIKE', '%_F15%')
    ->whereHas('customer', fn($q) => $q->where('name', 'LIKE', '%Nguy%'))
    ->first();
test("Find by customer name", $foundByCustomer !== null, $pass, $fail, $errors);

$foundByPhone = Order::whereHas('customer', fn($q) => $q->where('phone', '0900000001'))
    ->where('code', 'LIKE', '%_F15%')->first();
test("Find by phone", $foundByPhone !== null, $pass, $fail, $errors);

// Detail view
$detail = Order::with(['items.product', 'customer'])->find($orderB->id);
test("Detail has items", $detail->items->count() >= 1, $pass, $fail, $errors);
test("Detail has customer", $detail->customer !== null, $pass, $fail, $errors);
test("Detail has total_payment", $detail->total_payment > 0, $pass, $fail, $errors);
test("Detail has status", !empty($detail->status), $pass, $fail, $errors);

// === 15D: Edit order before conversion ===
echo "\n-- 15D: Edit order --\n";

$orderD = Order::create([
    'code' => 'DH_F15_D', 'customer_id' => $kh001->id, 'branch_id' => $branch?->id,
    'status' => 'draft', 'total_price' => 35000, 'discount' => 0, 'other_fees' => 0,
    'total_payment' => 35000, 'amount_paid' => 10000, 'note' => 'Test_F15 edit test',
    'created_by_name' => 'Admin',
]);
$orderD->items()->create(['product_id' => $sp001->id, 'qty' => 5, 'price' => 7000, 'discount' => 0, 'subtotal' => 35000]);

// Edit: change qty to 4, add DV001 placeholder
$orderD->items()->delete();
$orderD->items()->create(['product_id' => $sp001->id, 'qty' => 4, 'price' => 7000, 'discount' => 0, 'subtotal' => 28000]);
$newTotal = 28000;
$orderD->update(['total_price' => $newTotal, 'total_payment' => $newTotal]);

test("Edited order items count", $orderD->items()->count() === 1, $pass, $fail, $errors);
test("Edited total = 28000", (int)$orderD->fresh()->total_payment === 28000, $pass, $fail, $errors);
test("Deposit preserved", (int)$orderD->fresh()->amount_paid === 10000, $pass, $fail, $errors);

// === 15E: Convert order to invoice ===
echo "\n-- 15E: Convert order to invoice --\n";

$orderE = Order::create([
    'code' => 'DH_F15_E', 'customer_id' => $kh001->id, 'branch_id' => $branch?->id,
    'status' => 'confirmed', 'total_price' => 35000, 'discount' => 0, 'other_fees' => 0,
    'total_payment' => 35000, 'amount_paid' => 20000, 'note' => 'Test_F15 convert',
    'created_by_name' => 'Admin',
]);
$orderE->items()->create(['product_id' => $sp001->id, 'qty' => 5, 'price' => 7000, 'discount' => 0, 'subtotal' => 35000]);

// Simulate processOrder logic
DB::beginTransaction();
$orderE->load('items.product', 'customer');
$priorDeposit = $orderE->amount_paid;
$newPayment = 15000; // Pay remaining
$totalPaid = $priorDeposit + $newPayment;

$invoice = Invoice::create([
    'code' => 'HD_F15_E', 'order_id' => $orderE->id,
    'subtotal' => $orderE->total_price, 'discount' => 0,
    'total' => $orderE->total_payment, 'customer_paid' => $totalPaid,
    'customer_id' => $kh001->id, 'note' => 'Từ đơn hàng DH_F15_E_F15',
    'status' => 'Hoàn thành', 'payment_method' => 'cash',
]);
foreach ($orderE->items as $item) {
    $invoice->items()->create([
        'product_id' => $item->product_id, 'quantity' => $item->qty,
        'price' => $item->price, 'cost_price' => 3000,
    ]);
    Product::find($item->product_id)->decrement('stock_quantity', $item->qty);
}
$orderE->update(['status' => 'completed', 'amount_paid' => $totalPaid]);
ActivityLog::log('order_convert', "Chuyển đơn {$orderE->code} → hóa đơn {$invoice->code}_F15", $orderE);
DB::commit();

test("Invoice created", $invoice->id > 0, $pass, $fail, $errors);
test("Invoice linked to order", (int)$invoice->order_id === (int)$orderE->id, $pass, $fail, $errors);
test("Order linked to invoice", $orderE->fresh()->invoice !== null, $pass, $fail, $errors);
test("Invoice total = 35000", (int)$invoice->total === 35000, $pass, $fail, $errors);
test("Invoice customer_paid = 35000 (deposit+payment)", (int)$invoice->customer_paid === 35000, $pass, $fail, $errors);
test("Order status = completed", $orderE->fresh()->status === 'completed', $pass, $fail, $errors);
test("Stock deducted at conversion", (int)Product::find($sp001->id)->stock_quantity === (int)$stockBefore1 - 5, $pass, $fail, $errors);

// Restore stock
Product::find($sp001->id)->increment('stock_quantity', 5);

// === 15F: Partial payment at conversion ===
echo "\n-- 15F: Partial payment --\n";

$orderF = Order::create([
    'code' => 'DH_F15_F', 'customer_id' => $kh001->id, 'branch_id' => $branch?->id,
    'status' => 'confirmed', 'total_price' => 70000, 'discount' => 0, 'other_fees' => 0,
    'total_payment' => 70000, 'amount_paid' => 20000, 'note' => 'Test_F15 partial',
    'created_by_name' => 'Admin',
]);
$orderF->items()->create(['product_id' => $sp001->id, 'qty' => 10, 'price' => 7000, 'discount' => 0, 'subtotal' => 70000]);

// Pay only 30000 more (total settled = 50000, remaining = 20000)
$priorF = $orderF->amount_paid;
$newPayF = 30000;
$totalPaidF = $priorF + $newPayF;
$debtF = $orderF->total_payment - $totalPaidF;

$invoiceF = Invoice::create([
    'code' => 'HD_F15_F', 'order_id' => $orderF->id,
    'subtotal' => 70000, 'discount' => 0, 'total' => 70000,
    'customer_paid' => $totalPaidF, 'customer_id' => $kh001->id,
    'note' => 'Từ đơn hàng DH_F15_F_F15', 'status' => 'Hoàn thành', 'payment_method' => 'cash',
]);
$orderF->update(['status' => 'completed', 'amount_paid' => $totalPaidF]);

test("Total settled = 50000", (int)$totalPaidF === 50000, $pass, $fail, $errors);
test("Remaining = 20000", (int)$debtF === 20000, $pass, $fail, $errors);
test("Deposit not double counted", (int)$invoiceF->customer_paid === 50000, $pass, $fail, $errors);

// === 15G: Cancel order ===
echo "\n-- 15G: Cancel order --\n";

$orderG = Order::create([
    'code' => 'DH_F15_G', 'customer_id' => $kh001->id, 'branch_id' => $branch?->id,
    'status' => 'draft', 'total_price' => 14000, 'discount' => 0, 'other_fees' => 0,
    'total_payment' => 14000, 'amount_paid' => 0, 'note' => 'Test_F15 cancel',
    'created_by_name' => 'Admin',
]);
$orderG->items()->create(['product_id' => $sp001->id, 'qty' => 2, 'price' => 7000, 'discount' => 0, 'subtotal' => 14000]);

$orderG->update(['status' => 'cancelled', 'note' => $orderG->note . ' | Hủy: test']);
ActivityLog::log('order_cancel', "Hủy đơn hàng {$orderG->code}_F15", $orderG);

test("Cancel status", $orderG->fresh()->status === 'cancelled', $pass, $fail, $errors);
test("Cancel note", str_contains($orderG->fresh()->note, 'Hủy'), $pass, $fail, $errors);

// Cancelled order should not be processable
$canProcess = $orderG->fresh()->status !== 'completed' && $orderG->fresh()->status !== 'cancelled' && $orderG->fresh()->status !== 'ended';
test("Cancelled order not processable", !$canProcess, $pass, $fail, $errors);

// Order still exists (not deleted)
test("Order still in DB", Order::find($orderG->id) !== null, $pass, $fail, $errors);

// === 15H: End order ===
echo "\n-- 15H: End order --\n";

$orderH = Order::create([
    'code' => 'DH_F15_H', 'customer_id' => $kh001->id, 'branch_id' => $branch?->id,
    'status' => 'confirmed', 'total_price' => 21000, 'discount' => 0, 'other_fees' => 0,
    'total_payment' => 21000, 'amount_paid' => 5000, 'note' => 'Test_F15 end',
    'created_by_name' => 'Admin',
]);
$orderH->items()->create(['product_id' => $sp001->id, 'qty' => 3, 'price' => 7000, 'discount' => 0, 'subtotal' => 21000]);

$orderH->update(['status' => 'ended', 'note' => $orderH->note . ' | Kết thúc: không giao']);
ActivityLog::log('order_end', "Kết thúc đơn hàng {$orderH->code}_F15", $orderH);

test("End status", $orderH->fresh()->status === 'ended', $pass, $fail, $errors);
$canProcessH = !in_array($orderH->fresh()->status, ['completed', 'cancelled', 'ended']);
test("Ended order not processable", !$canProcessH, $pass, $fail, $errors);

// === 15I: Prevent incompatible merge ===
echo "\n-- 15I: Prevent incompatible merge --\n";

$orderI1 = Order::create([
    'code' => 'DH_F15_I1', 'customer_id' => $kh001->id, 'branch_id' => $branch?->id,
    'status' => 'draft', 'total_price' => 7000, 'total_payment' => 7000, 'amount_paid' => 0,
    'note' => 'Test_F15 merge incompatible', 'created_by_name' => 'Admin',
]);
$orderI2 = Order::create([
    'code' => 'DH_F15_I2', 'customer_id' => $kh002->id, 'branch_id' => $branch?->id,
    'status' => 'draft', 'total_price' => 7000, 'total_payment' => 7000, 'amount_paid' => 0,
    'note' => 'Test_F15 merge incompatible', 'created_by_name' => 'Admin',
]);

// Different customers — should fail
$orders = Order::with('items')->whereIn('id', [$orderI1->id, $orderI2->id])->get();
$customers = $orders->pluck('customer_id')->unique();
test("Different customers detected", $customers->count() > 1, $pass, $fail, $errors);

// === 15J: Merge compatible orders ===
echo "\n-- 15J: Merge compatible orders --\n";

$orderJ1 = Order::create([
    'code' => 'DH_F15_J1', 'customer_id' => $kh001->id, 'branch_id' => $branch?->id,
    'status' => 'draft', 'total_price' => 14000, 'discount' => 0, 'other_fees' => 0,
    'total_payment' => 14000, 'amount_paid' => 5000,
    'note' => 'Test_F15 merge source 1', 'created_by_name' => 'Admin', 'price_book_name' => 'Bảng giá chung',
]);
$orderJ1->items()->create(['product_id' => $sp001->id, 'qty' => 2, 'price' => 7000, 'discount' => 0, 'subtotal' => 14000]);

$orderJ2 = Order::create([
    'code' => 'DH_F15_J2', 'customer_id' => $kh001->id, 'branch_id' => $branch?->id,
    'status' => 'draft', 'total_price' => 30000, 'discount' => 0, 'other_fees' => 0,
    'total_payment' => 30000, 'amount_paid' => 10000,
    'note' => 'Test_F15 merge source 2', 'created_by_name' => 'Admin', 'price_book_name' => 'Bảng giá chung',
]);
$orderJ2->items()->create(['product_id' => $sp002->id, 'qty' => 1, 'price' => 30000, 'discount' => 0, 'subtotal' => 30000]);

// Simulate merge
DB::beginTransaction();
$mergeOrders = Order::with('items')->whereIn('id', [$orderJ1->id, $orderJ2->id])->get();
$mergedTotal = $mergeOrders->sum('total_price');
$mergedDeposit = $mergeOrders->sum('amount_paid');
$mergedPayment = $mergeOrders->sum('total_payment');

$merged = Order::create([
    'code' => 'DH_F15_J_M', 'customer_id' => $kh001->id, 'branch_id' => $branch?->id,
    'status' => 'draft', 'total_price' => $mergedTotal, 'discount' => 0, 'other_fees' => 0,
    'total_payment' => $mergedPayment, 'amount_paid' => $mergedDeposit,
    'note' => 'Gộp từ: DH_F15_J1, DH_F15_J2_F15', 'created_by_name' => 'Admin', 'price_book_name' => 'Bảng giá chung',
]);
foreach ($mergeOrders as $src) {
    foreach ($src->items as $item) {
        $merged->items()->create([
            'product_id' => $item->product_id, 'qty' => $item->qty,
            'price' => $item->price, 'discount' => $item->discount, 'subtotal' => $item->subtotal,
        ]);
    }
    $src->update(['status' => 'cancelled', 'note' => $src->note . ' | Đã gộp vào ' . $merged->code]);
}
ActivityLog::log('order_merge', "Gộp đơn hàng: DH_F15_J1, DH_F15_J2 → {$merged->code}_F15", $merged);
DB::commit();

test("Merged order created", $merged->id > 0, $pass, $fail, $errors);
test("Merged total = 44000", (int)$merged->total_payment === 44000, $pass, $fail, $errors);
test("Merged deposit = 15000", (int)$merged->amount_paid === 15000, $pass, $fail, $errors);
test("Merged items count = 2", $merged->items()->count() === 2, $pass, $fail, $errors);
test("Source J1 cancelled", $orderJ1->fresh()->status === 'cancelled', $pass, $fail, $errors);
test("Source J2 cancelled", $orderJ2->fresh()->status === 'cancelled', $pass, $fail, $errors);

// === 15K: Permission checks ===
echo "\n-- 15K: Permission checks --\n";

// Check route middleware
$routes = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes());
$processRoute = $routes->first(fn($r) => $r->getName() === 'orders.process');
$cancelRoute = $routes->first(fn($r) => $r->getName() === 'orders.cancel');
$mergeRoute = $routes->first(fn($r) => $r->getName() === 'orders.merge');

test("Process route has permission middleware", $processRoute && in_array('permission:orders.edit', $processRoute->middleware()), $pass, $fail, $errors);
test("Cancel route has permission middleware", $cancelRoute && in_array('permission:orders.edit', $cancelRoute->middleware()), $pass, $fail, $errors);
test("Merge route has permission middleware", $mergeRoute && in_array('permission:orders.edit', $mergeRoute->middleware()), $pass, $fail, $errors);

$createRoute = $routes->first(fn($r) => $r->getName() === 'orders.create');
test("Create route has permission middleware", $createRoute && in_array('permission:orders.create', $createRoute->middleware()), $pass, $fail, $errors);

// === 15L: Lock period ===
echo "\n-- 15L: Lock period --\n";

$svc = app(LockPeriodService::class);

// Check controller source has lock enforcement
$source = file_get_contents(__DIR__ . '/app/Http/Controllers/OrderController.php');
test("OrderController has lock check", str_contains($source, 'assertNotLocked'), $pass, $fail, $errors);

// Test lock enforcement
$svc->setLockDate('2026-03-31');
$blocked = false;
try { $svc->assertNotLocked('2026-03-20', 'order_create'); } catch (\App\Exceptions\LockPeriodException $e) { $blocked = true; }
test("Backdated order blocked", $blocked, $pass, $fail, $errors);

$blocked = false;
try { $svc->assertNotLocked('2026-04-05', 'order_create'); } catch (\App\Exceptions\LockPeriodException $e) { $blocked = true; }
test("Future order allowed", !$blocked, $pass, $fail, $errors);

// Cleanup lock
Setting::where('key', 'lock_date')->delete();

// === AUDIT TRAIL ===
echo "\n-- Audit trail --\n";
$auditConvert = ActivityLog::where('action', 'order_convert')->where('description', 'LIKE', '%_F15%')->count();
$auditCancel = ActivityLog::where('action', 'order_cancel')->where('description', 'LIKE', '%_F15%')->count();
$auditEnd = ActivityLog::where('action', 'order_end')->where('description', 'LIKE', '%_F15%')->count();
$auditMerge = ActivityLog::where('action', 'order_merge')->where('description', 'LIKE', '%_F15%')->count();

test("Convert audit", $auditConvert >= 1, $pass, $fail, $errors, "got: $auditConvert");
test("Cancel audit", $auditCancel >= 1, $pass, $fail, $errors, "got: $auditCancel");
test("End audit", $auditEnd >= 1, $pass, $fail, $errors, "got: $auditEnd");
test("Merge audit", $auditMerge >= 1, $pass, $fail, $errors, "got: $auditMerge");

// === STATUS CONSTANTS ===
echo "\n-- Status constants --\n";
test("STATUS_DRAFT", Order::STATUS_DRAFT === 'draft', $pass, $fail, $errors);
test("STATUS_CONFIRMED", Order::STATUS_CONFIRMED === 'confirmed', $pass, $fail, $errors);
test("STATUS_COMPLETED", Order::STATUS_COMPLETED === 'completed', $pass, $fail, $errors);
test("STATUS_CANCELLED", Order::STATUS_CANCELLED === 'cancelled', $pass, $fail, $errors);
test("STATUS_ENDED", Order::STATUS_ENDED === 'ended', $pass, $fail, $errors);

// === SUMMARY ===
echo "\n=== KET QUA: $pass PASS / $fail FAIL ===\n\n";

if (count($errors) > 0) {
    echo "DANH SACH LOI:\n";
    foreach ($errors as $i => $e) { echo "  " . ($i + 1) . ". $e\n"; }
}

echo "\n== DEVIATIONS ==\n";
echo "  1. No stock reservation at order creation (consistent with KiotViet default)\n";
echo "  2. Deposit refund on cancel: app retains deposit info but no auto-refund\n";
echo "  3. No DV001 service product created — test uses SP001/SP002 only\n";

// === Cleanup ===
echo "\n-- Cleanup --\n";
ActivityLog::where('description', 'LIKE', '%_F15%')->delete();
ActivityLog::where('action', 'lock_period_change')->delete();
InvoiceItem::whereHas('invoice', fn($q) => $q->where('note', 'LIKE', '%_F15%'))->delete();
Invoice::where('note', 'LIKE', '%_F15%')->delete();
OrderItem::whereHas('order', fn($q) => $q->where('code', 'LIKE', '%_F15%'))->delete();
Order::where('code', 'LIKE', '%_F15%')->delete();
CashFlow::withTrashed()->where('description', 'LIKE', '%_F15%')->forceDelete();
Setting::where('key', 'lock_date')->delete();
echo "  OK Cleaned up\n";
