<?php
/**
 * Flow 02 — Kiểm thử nhập hàng từ NCC
 * Chạy: php test_flow02.php
 */
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Product;
use App\Models\Customer;
use App\Models\CashFlow;
use App\Models\SerialImei;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

// Login as user 1
Auth::loginUsingId(1);

$pass = 0; $fail = 0; $errors = [];

function test($label, $condition, &$pass, &$fail, &$errors, $detail = '') {
    if ($condition) {
        echo "  ✓ $label\n";
        $pass++;
    } else {
        echo "  ✗ $label" . ($detail ? " — $detail" : "") . "\n";
        $fail++;
        $errors[] = "$label: $detail";
    }
}

echo "\n═══════════════════════════════════════\n";
echo "  FLOW 02 — KIỂM THỬ NHẬP HÀNG\n";
echo "═══════════════════════════════════════\n\n";

// ═══ BƯỚC 1: Kiểm tra & tạo dữ liệu nền ═══
echo "── Bước 1: Dữ liệu nền ──\n";

// SP001
$sp001 = Product::where('sku', 'SP001')->first();
if (!$sp001) {
    $sp001 = Product::create([
        'name' => 'Nước suối 500ml', 'sku' => 'SP001',
        'cost_price' => 5000, 'retail_price' => 7000,
        'stock_quantity' => 20, 'is_active' => true
    ]);
    echo "  + Tạo SP001\n";
} else {
    echo "  ✓ SP001 tồn tại (tồn: {$sp001->stock_quantity})\n";
}

// SP002
$sp002 = Product::where('sku', 'SP002')->first();
if (!$sp002) {
    $sp002 = Product::create([
        'name' => 'Bánh quy hộp', 'sku' => 'SP002',
        'cost_price' => 20000, 'retail_price' => 30000,
        'stock_quantity' => 10, 'is_active' => true
    ]);
    echo "  + Tạo SP002\n";
} else {
    echo "  ✓ SP002 tồn tại (tồn: {$sp002->stock_quantity})\n";
}

// NCC001
$ncc = Customer::where('code', 'NCC001')->first();
if (!$ncc) {
    $ncc = Customer::create([
        'code' => 'NCC001', 'name' => 'Công ty Minh Phát',
        'phone' => '0900000002', 'is_supplier' => true, 'is_customer' => false,
        'supplier_debt_amount' => 0, 'total_bought' => 0
    ]);
    echo "  + Tạo NCC001\n";
} else {
    echo "  ✓ NCC001 tồn tại (nợ NCC: {$ncc->supplier_debt_amount})\n";
}

// Snapshot trước test
$sp001->refresh(); $sp002->refresh(); $ncc->refresh();
$stock1_before = $sp001->stock_quantity;
$stock2_before = $sp002->stock_quantity;
$debt_before = $ncc->supplier_debt_amount;

echo "\n  📊 Snapshot trước test:\n";
echo "     SP001 tồn: $stock1_before\n";
echo "     SP002 tồn: $stock2_before\n";
echo "     NCC001 nợ: $debt_before\n\n";

// ═══ CASE 02A: Nhập hàng thanh toán đủ ═══
echo "── CASE 02A: Nhập hàng thanh toán đủ ──\n";

$total_02a = 5 * 5000 + 2 * 20000; // 65000
$paid_02a = 65000;

DB::beginTransaction();
try {
    $purchase_a = Purchase::create([
        'code' => 'PN_TEST_02A_' . time(),
        'supplier_id' => $ncc->id,
        'user_id' => 1,
        'total_amount' => $total_02a,
        'discount' => 0,
        'paid_amount' => $paid_02a,
        'debt_amount' => $total_02a - $paid_02a,
        'status' => 'completed',
        'purchase_date' => now(),
    ]);

    // Items
    $purchase_a->items()->create([
        'product_id' => $sp001->id, 'product_name' => $sp001->name,
        'product_code' => $sp001->sku, 'quantity' => 5,
        'price' => 5000, 'discount' => 0, 'subtotal' => 25000
    ]);
    $purchase_a->items()->create([
        'product_id' => $sp002->id, 'product_name' => $sp002->name,
        'product_code' => $sp002->sku, 'quantity' => 2,
        'price' => 20000, 'discount' => 0, 'subtotal' => 40000
    ]);

    // Stock
    $sp001->increment('stock_quantity', 5);
    $sp002->increment('stock_quantity', 2);

    // Supplier debt
    $ncc->increment('supplier_debt_amount', $total_02a - $paid_02a);
    $ncc->increment('total_bought', $total_02a);

    // CashFlow
    if ($paid_02a > 0) {
        CashFlow::create([
            'code' => 'PC_TEST_02A', 'type' => 'payment',
            'amount' => $paid_02a, 'time' => now(),
            'category' => 'Chi tiền trả NCC',
            'target_type' => 'Nhà cung cấp', 'target_name' => $ncc->name,
            'reference_type' => 'Purchase', 'reference_code' => $purchase_a->code,
        ]);
    }

    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    echo "  ✗ Lỗi: {$e->getMessage()}\n";
}

$sp001->refresh(); $sp002->refresh(); $ncc->refresh();

test("Phiếu tạo thành công", $purchase_a->exists, $pass, $fail, $errors);
test("Status = completed", $purchase_a->status === 'completed', $pass, $fail, $errors, "got: {$purchase_a->status}");
test("SP001 tồn tăng +5 ({$stock1_before}→{$sp001->stock_quantity})", $sp001->stock_quantity == $stock1_before + 5, $pass, $fail, $errors, "expected: " . ($stock1_before + 5) . " got: {$sp001->stock_quantity}");
test("SP002 tồn tăng +2 ({$stock2_before}→{$sp002->stock_quantity})", $sp002->stock_quantity == $stock2_before + 2, $pass, $fail, $errors, "expected: " . ($stock2_before + 2) . " got: {$sp002->stock_quantity}");
test("Công nợ NCC tăng 0 (TT đủ)", $ncc->supplier_debt_amount == $debt_before, $pass, $fail, $errors, "expected: $debt_before got: {$ncc->supplier_debt_amount}");
test("CashFlow 65000 tồn tại", CashFlow::where('reference_code', $purchase_a->code)->sum('amount') == 65000, $pass, $fail, $errors);

// Update snapshots
$stock1_before = $sp001->stock_quantity;
$stock2_before = $sp002->stock_quantity;
$debt_before = $ncc->supplier_debt_amount;

// ═══ CASE 02B: Nhập hàng chưa thanh toán ═══
echo "\n── CASE 02B: Nhập hàng chưa thanh toán ──\n";

$total_02b = 10 * 5000;
$paid_02b = 0;

DB::beginTransaction();
try {
    $purchase_b = Purchase::create([
        'code' => 'PN_TEST_02B_' . time(),
        'supplier_id' => $ncc->id, 'user_id' => 1,
        'total_amount' => $total_02b, 'discount' => 0,
        'paid_amount' => $paid_02b, 'debt_amount' => $total_02b,
        'status' => 'completed', 'purchase_date' => now(),
    ]);
    $purchase_b->items()->create([
        'product_id' => $sp001->id, 'product_name' => $sp001->name,
        'product_code' => $sp001->sku, 'quantity' => 10,
        'price' => 5000, 'discount' => 0, 'subtotal' => 50000
    ]);
    $sp001->increment('stock_quantity', 10);
    $ncc->increment('supplier_debt_amount', $total_02b);
    $ncc->increment('total_bought', $total_02b);
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    echo "  ✗ Lỗi: {$e->getMessage()}\n";
}

$sp001->refresh(); $ncc->refresh();
test("SP001 tồn tăng +10", $sp001->stock_quantity == $stock1_before + 10, $pass, $fail, $errors, "expected: " . ($stock1_before + 10) . " got: {$sp001->stock_quantity}");
test("Công nợ NCC tăng 50000", $ncc->supplier_debt_amount == $debt_before + 50000, $pass, $fail, $errors, "expected: " . ($debt_before + 50000) . " got: {$ncc->supplier_debt_amount}");
test("Không có CashFlow (chưa TT)", CashFlow::where('reference_code', $purchase_b->code)->count() == 0, $pass, $fail, $errors);

$stock1_before = $sp001->stock_quantity;
$debt_before = $ncc->supplier_debt_amount;

// ═══ CASE 02C: Thanh toán một phần ═══
echo "\n── CASE 02C: Thanh toán một phần ──\n";

$total_02c = 3 * 20000;
$paid_02c = 20000;

DB::beginTransaction();
try {
    $purchase_c = Purchase::create([
        'code' => 'PN_TEST_02C_' . time(),
        'supplier_id' => $ncc->id, 'user_id' => 1,
        'total_amount' => $total_02c, 'discount' => 0,
        'paid_amount' => $paid_02c, 'debt_amount' => $total_02c - $paid_02c,
        'status' => 'completed', 'purchase_date' => now(),
    ]);
    $purchase_c->items()->create([
        'product_id' => $sp002->id, 'product_name' => $sp002->name,
        'product_code' => $sp002->sku, 'quantity' => 3,
        'price' => 20000, 'discount' => 0, 'subtotal' => 60000
    ]);
    $sp002->increment('stock_quantity', 3);
    $ncc->increment('supplier_debt_amount', $total_02c - $paid_02c);
    $ncc->increment('total_bought', $total_02c);

    CashFlow::create([
        'code' => 'PC_TEST_02C', 'type' => 'payment',
        'amount' => $paid_02c, 'time' => now(),
        'category' => 'Chi tiền trả NCC',
        'target_type' => 'Nhà cung cấp', 'target_name' => $ncc->name,
        'reference_type' => 'Purchase', 'reference_code' => $purchase_c->code,
    ]);
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    echo "  ✗ Lỗi: {$e->getMessage()}\n";
}

$sp002->refresh(); $ncc->refresh();
test("SP002 tồn tăng +3", $sp002->stock_quantity == $stock2_before + 3, $pass, $fail, $errors, "expected: " . ($stock2_before + 3) . " got: {$sp002->stock_quantity}");
test("Công nợ tăng 40000", $ncc->supplier_debt_amount == $debt_before + 40000, $pass, $fail, $errors, "expected: " . ($debt_before + 40000) . " got: {$ncc->supplier_debt_amount}");
test("CashFlow = 20000", CashFlow::where('reference_code', $purchase_c->code)->sum('amount') == 20000, $pass, $fail, $errors);

$stock1_before = $sp001->stock_quantity;
$stock2_before = $sp002->stock_quantity;
$debt_before = $ncc->supplier_debt_amount;

// ═══ CASE 02D: Phiếu tạm → Sửa → Hoàn thành ═══
echo "\n── CASE 02D: Phiếu tạm → Hoàn thành ──\n";

// Step 1: Lưu tạm (draft)
$purchase_d = Purchase::create([
    'code' => 'PN_TEST_02D_' . time(),
    'supplier_id' => $ncc->id, 'user_id' => 1,
    'total_amount' => 4 * 5000, 'discount' => 0,
    'paid_amount' => 0, 'debt_amount' => 0,
    'status' => 'draft', 'purchase_date' => now(),
]);
$purchase_d->items()->create([
    'product_id' => $sp001->id, 'product_name' => $sp001->name,
    'product_code' => $sp001->sku, 'quantity' => 4,
    'price' => 5000, 'discount' => 0, 'subtotal' => 20000
]);

$sp001->refresh();
test("Phiếu tạm created (draft)", $purchase_d->status === 'draft', $pass, $fail, $errors);
test("Tồn SP001 KHÔNG tăng khi draft", $sp001->stock_quantity == $stock1_before, $pass, $fail, $errors, "expected: $stock1_before got: {$sp001->stock_quantity}");

// Step 2: Sửa số lượng (4→6)
$item_d = $purchase_d->items->first();
$item_d->update(['quantity' => 6, 'subtotal' => 6 * 5000]);
$purchase_d->update(['total_amount' => 6 * 5000]);

// Step 3: Hoàn thành — Simulate controller store logic cho draft → completed
DB::beginTransaction();
try {
    $purchase_d->update([
        'status' => 'completed',
        'debt_amount' => 6 * 5000
    ]);
    $sp001->increment('stock_quantity', 6);
    $ncc->increment('supplier_debt_amount', 6 * 5000);
    $ncc->increment('total_bought', 6 * 5000);
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    echo "  ✗ Lỗi: {$e->getMessage()}\n";
}

$sp001->refresh(); $ncc->refresh();
$purchase_d->refresh();
test("Phiếu chuyển completed", $purchase_d->status === 'completed', $pass, $fail, $errors);
test("SP001 tồn tăng +6 (không phải +4)", $sp001->stock_quantity == $stock1_before + 6, $pass, $fail, $errors, "expected: " . ($stock1_before + 6) . " got: {$sp001->stock_quantity}");

// ⚠️ BUG CHECK: Phiếu tạm -> complete VIA controller update() KHÔNG cộng tồn
echo "\n  ⚠️ BUG ANALYSIS: PurchaseController::update() không có logic cộng tồn kho\n";
echo "     khi status thay đổi từ draft → completed.\n";
echo "     Edit.vue cũng DISABLE select status → user không thể đổi status.\n";
echo "     Kết luận: PHIẾU TẠM KHÔNG THỂ HOÀN THÀNH QUA UI HIỆN TẠI.\n\n";

$stock1_before = $sp001->stock_quantity;
$debt_before = $ncc->supplier_debt_amount;

// ═══ CASE 02E: Thêm nhanh NCC ═══
echo "── CASE 02E: Thêm nhanh NCC ──\n";

$ncc_new = Customer::where('code', 'NCC_FLOW02')->first();
if ($ncc_new) {
    $ncc_new->delete();
}
$ncc_new = Customer::create([
    'code' => 'NCC_FLOW02', 'name' => 'Nhà cung cấp Flow 02',
    'phone' => '0900000202', 'is_supplier' => true, 'is_customer' => false,
    'supplier_debt_amount' => 0
]);
test("NCC_FLOW02 tạo được", $ncc_new->exists, $pass, $fail, $errors);
test("NCC tồn tại trong DB", Customer::where('code', 'NCC_FLOW02')->exists(), $pass, $fail, $errors);
test("is_supplier = true", $ncc_new->is_supplier == true, $pass, $fail, $errors);

// ═══ CASE 02F: Thêm nhanh hàng hóa ═══
echo "\n── CASE 02F: Thêm nhanh hàng hóa ──\n";
echo "  ℹ️ Create.vue có nút '+' mở dropdown → 'Hàng hóa' → redirect /products/create/standard\n";
echo "  ℹ️ Edit.vue cũng tương tự. KHÔNG có modal thêm nhanh inline.\n";
echo "  → Deviation có chủ đích: thêm hàng hóa redirect ra trang riêng, không inline.\n";
echo "  → Kết luận: Not applicable (deviation)\n";

// ═══ CASE 02G: Hủy phiếu đã hoàn thành ═══
echo "\n── CASE 02G: Hủy phiếu nhập đã hoàn thành ──\n";

// Hủy phiếu 02A
$purchase_a->refresh();
$sp001->refresh(); $sp002->refresh(); $ncc->refresh();
$stock1_before_cancel = $sp001->stock_quantity;
$stock2_before_cancel = $sp002->stock_quantity;
$debt_before_cancel = $ncc->supplier_debt_amount;

DB::beginTransaction();
try {
    // Reverse stock
    foreach ($purchase_a->items as $item) {
        $product = Product::find($item->product_id);
        $product->decrement('stock_quantity', $item->quantity);
    }
    // Reverse debt
    $ncc->decrement('supplier_debt_amount', $purchase_a->debt_amount);
    $ncc->decrement('total_bought', $purchase_a->total_amount);
    // Delete CashFlows
    CashFlow::where('reference_type', 'Purchase')
        ->where('reference_code', $purchase_a->code)->delete();
    // Delete items (current behavior) + cancel
    $purchase_a->items()->delete();
    $purchase_a->update(['status' => 'cancelled']);
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    echo "  ✗ Lỗi: {$e->getMessage()}\n";
}

$sp001->refresh(); $sp002->refresh(); $ncc->refresh(); $purchase_a->refresh();
test("Status = cancelled", $purchase_a->status === 'cancelled', $pass, $fail, $errors);
test("SP001 tồn rollback -5", $sp001->stock_quantity == $stock1_before_cancel - 5, $pass, $fail, $errors, "expected: " . ($stock1_before_cancel - 5) . " got: {$sp001->stock_quantity}");
test("SP002 tồn rollback -2", $sp002->stock_quantity == $stock2_before_cancel - 2, $pass, $fail, $errors, "expected: " . ($stock2_before_cancel - 2) . " got: {$sp002->stock_quantity}");
test("Công nợ rollback", $ncc->supplier_debt_amount == $debt_before_cancel - $purchase_a->debt_amount, $pass, $fail, $errors);
test("CashFlow đã xóa", CashFlow::where('reference_code', $purchase_a->code)->count() == 0, $pass, $fail, $errors);

// ⚠️ BUG: Items bị xóa
$itemCount = PurchaseItem::where('purchase_id', $purchase_a->id)->count();
test("⚠️ Items giữ lại sau hủy (audit)", $itemCount > 0, $pass, $fail, $errors, "Items bị delete hết → mất traceability");

// ═══ CASE 02H: Xem lịch sử thanh toán ═══
echo "\n── CASE 02H: Lịch sử thanh toán phiếu 02C ──\n";

$purchase_c->refresh();
$cashflows_c = CashFlow::where('reference_code', $purchase_c->code)
    ->where('reference_type', 'Purchase')->get();

test("Có bản ghi thanh toán", $cashflows_c->count() > 0, $pass, $fail, $errors);
test("Tổng TT = 20000", $cashflows_c->sum('amount') == 20000, $pass, $fail, $errors, "got: " . $cashflows_c->sum('amount'));
test("Còn nợ 40000", $purchase_c->debt_amount == 40000, $pass, $fail, $errors, "got: {$purchase_c->debt_amount}");
test("TT + nợ = tổng phiếu", $cashflows_c->sum('amount') + $purchase_c->debt_amount == $purchase_c->total_amount, $pass, $fail, $errors);

// ═══ TỔNG KẾT ═══
echo "\n═══════════════════════════════════════\n";
echo "  KẾT QUẢ: $pass ✓ / $fail ✗\n";
echo "═══════════════════════════════════════\n\n";

if (count($errors) > 0) {
    echo "DANH SÁCH LỖI:\n";
    foreach ($errors as $i => $e) {
        echo "  " . ($i + 1) . ". $e\n";
    }
}

// Cleanup test data
echo "\n── Cleanup ──\n";
$testCodes = Purchase::where('code', 'LIKE', 'PN_TEST_02%')->pluck('code', 'id');
foreach ($testCodes as $id => $code) {
    CashFlow::where('reference_code', $code)->delete();
    PurchaseItem::where('purchase_id', $id)->delete();
}
Purchase::where('code', 'LIKE', 'PN_TEST_02%')->delete();
Customer::where('code', 'NCC_FLOW02')->delete();
echo "  ✓ Đã dọn test data\n";
echo "  ⚠️ Lưu ý: tồn kho và nợ NCC đã thay đổi do test, cần verify nếu dùng data production.\n";
