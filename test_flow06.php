<?php
/**
 * Flow 06 -- Kiem thu Cong no nha cung cap
 */
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Product;
use App\Models\Customer;
use App\Models\CashFlow;
use App\Models\SupplierDebtTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

Auth::loginUsingId(1);

$pass = 0; $fail = 0; $errors = [];

function test($label, $condition, &$pass, &$fail, &$errors, $detail = '') {
    if ($condition) {
        echo "  PASS $label\n";
        $pass++;
    } else {
        echo "  FAIL $label" . ($detail ? " -- $detail" : "") . "\n";
        $fail++;
        $errors[] = "$label: $detail";
    }
}

echo "\n=== FLOW 06 -- KIEM THU CONG NO NHA CUNG CAP ===\n\n";

// === CLEANUP ===
Purchase::where('code', 'LIKE', 'PN_F06%')->each(function ($p) {
    PurchaseItem::where('purchase_id', $p->id)->delete();
    SupplierDebtTransaction::where('purchase_id', $p->id)->delete();
    $p->delete();
});
SupplierDebtTransaction::where('note', 'LIKE', '%Flow06%')->delete();
CashFlow::where('description', 'LIKE', '%Flow06%')->orWhere('reference_code', 'LIKE', 'PCPN_F06%')->delete();

// === SETUP ===
echo "-- Setup --\n";

$sp001 = Product::where('sku', 'SP001')->first();
$sp002 = Product::where('sku', 'SP002')->first();
$ncc001 = Customer::where('code', 'NCC001')->first();

if (!$ncc001) {
    $ncc001 = Customer::firstOrCreate(
        ['code' => 'NCC001'],
        ['name' => 'Cong ty Minh Phat', 'is_supplier' => true, 'is_customer' => false, 'supplier_debt_amount' => 0]
    );
}

if (!$sp001 || !$sp002) {
    echo "  FAIL Missing SP001 or SP002.\n";
    exit(1);
}

// Save initial state
$initDebt = $ncc001->supplier_debt_amount;

// Clear existing debt txs for this supplier to get clean slate
SupplierDebtTransaction::where('supplier_id', $ncc001->id)->delete();

// Purchase A: total=1,000,000 paid=400,000 debt=600,000
$pnA = Purchase::create([
    'code' => 'PN_F06A_' . time(),
    'supplier_id' => $ncc001->id,
    'total_amount' => 1000000, 'paid_amount' => 400000, 'debt_amount' => 600000,
    'discount' => 0, 'status' => 'completed',
    'purchase_date' => now()->subDays(5), 'user_id' => 1,
]);
$pnA->items()->create([
    'product_id' => $sp001->id, 'product_name' => $sp001->name, 'product_code' => $sp001->sku ?? $sp001->code ?? 'SP001',
    'quantity' => 100, 'price' => 10000, 'subtotal' => 1000000,
]);

// Purchase B: total=800,000 paid=0 debt=800,000
$pnB = Purchase::create([
    'code' => 'PN_F06B_' . time(),
    'supplier_id' => $ncc001->id,
    'total_amount' => 800000, 'paid_amount' => 0, 'debt_amount' => 800000,
    'discount' => 0, 'status' => 'completed',
    'purchase_date' => now()->subDays(3), 'user_id' => 1,
]);
$pnB->items()->create([
    'product_id' => $sp002->id, 'product_name' => $sp002->name, 'product_code' => $sp002->sku ?? $sp002->code ?? 'SP002',
    'quantity' => 50, 'price' => 16000, 'subtotal' => 800000,
]);

// Set supplier cached debt
$ncc001->update(['supplier_debt_amount' => 1400000]);

echo "  OK NCC001: {$ncc001->name} (id={$ncc001->id})\n";
echo "  OK PN_A: {$pnA->code} debt=600K\n";
echo "  OK PN_B: {$pnB->code} debt=800K\n";
echo "  OK Total debt=1,400,000\n";

$ctrl = new \App\Http\Controllers\SupplierController();

// === 06A: Xem cong no NCC ===
echo "\n-- 06A: Xem chi tiet cong no NCC --\n";

// Seed debt transactions first (the method auto-seeds on first call)
$debtResp = $ctrl->debtTransactions($ncc001->id);
$debtData = json_decode($debtResp->getContent(), true);

test("API tra ve entries", isset($debtData['entries']) && count($debtData['entries']) > 0, $pass, $fail, $errors, "count: " . count($debtData['entries'] ?? []));
test("Summary net > 0", isset($debtData['summary']['net']) && $debtData['summary']['net'] > 0, $pass, $fail, $errors, "net: " . ($debtData['summary']['net'] ?? 'null'));

// Check PN_A and PN_B appear
$codes = array_column($debtData['entries'] ?? [], 'code');
test("PN_A trong lich su", in_array($pnA->code, $codes), $pass, $fail, $errors);
test("PN_B trong lich su", in_array($pnB->code, $codes), $pass, $fail, $errors);

// === 06B: Thanh toan tu phan bo 500,000 ===
echo "\n-- 06B: Thanh toan auto-allocate 500K --\n";

$ncc001->refresh();
$debtBefore06B = $ncc001->supplier_debt_amount;

$req = new \Illuminate\Http\Request();
$req->merge([
    'amount' => 500000,
    'note' => 'Test Flow06B auto',
    'mode' => 'auto',
]);
$resp = $ctrl->recordPayment($req, $ncc001->id);
$result = json_decode($resp->getContent(), true);

$ncc001->refresh(); $pnA->refresh(); $pnB->refresh();

test("API success", $result['success'] == true, $pass, $fail, $errors);
test("NCC debt giam 500K", $ncc001->supplier_debt_amount == $debtBefore06B - 500000, $pass, $fail, $errors, "got: {$ncc001->supplier_debt_amount}");

// Auto-allocate: PN_A had 600K debt, should get 500K -> remaining 100K
test("PN_A debt con 100K", $pnA->debt_amount == 100000, $pass, $fail, $errors, "got: {$pnA->debt_amount}");
test("PN_A paid = 900K", $pnA->paid_amount == 900000, $pass, $fail, $errors, "got: {$pnA->paid_amount}");
test("PN_B debt khong doi 800K", $pnB->debt_amount == 800000, $pass, $fail, $errors, "got: {$pnB->debt_amount}");

// Check CashFlow
$cf06B = CashFlow::where('reference_type', 'SupplierPayment')
    ->where('target_id', $ncc001->id)
    ->where('amount', 500000)
    ->latest()->first();
test("CashFlow phieu chi 500K", $cf06B !== null, $pass, $fail, $errors);

// Check SupplierDebtTransaction
$stx06B = SupplierDebtTransaction::where('supplier_id', $ncc001->id)
    ->where('type', 'payment')
    ->where('amount', -500000)
    ->latest()->first();
test("DebtTransaction -500K", $stx06B !== null, $pass, $fail, $errors);

// === 06C: Thanh toan manual allocation ===
echo "\n-- 06C: Thanh toan manual (PN_A:50K, PN_B:200K) --\n";

$ncc001->refresh(); $pnA->refresh(); $pnB->refresh();
$debtBefore06C = $ncc001->supplier_debt_amount;
$pnA_debt_before = $pnA->debt_amount;
$pnB_debt_before = $pnB->debt_amount;

$req = new \Illuminate\Http\Request();
$req->merge([
    'amount' => 250000,
    'note' => 'Test Flow06C manual',
    'mode' => 'manual',
    'allocations' => [
        ['purchase_id' => $pnA->id, 'amount' => 50000],
        ['purchase_id' => $pnB->id, 'amount' => 200000],
    ],
]);
$resp = $ctrl->recordPayment($req, $ncc001->id);
$result = json_decode($resp->getContent(), true);

$ncc001->refresh(); $pnA->refresh(); $pnB->refresh();

test("API success", $result['success'] == true, $pass, $fail, $errors);
test("NCC debt giam 250K", $ncc001->supplier_debt_amount == $debtBefore06C - 250000, $pass, $fail, $errors, "got: {$ncc001->supplier_debt_amount}");
test("PN_A debt giam 50K", $pnA->debt_amount == $pnA_debt_before - 50000, $pass, $fail, $errors, "got: {$pnA->debt_amount}");
test("PN_B debt giam 200K", $pnB->debt_amount == $pnB_debt_before - 200000, $pass, $fail, $errors, "got: {$pnB->debt_amount}");

// === 06D: Thanh toan het toan bo ===
echo "\n-- 06D: Thanh toan het toan bo --\n";

$ncc001->refresh();
$remainingDebt = $ncc001->supplier_debt_amount;

$req = new \Illuminate\Http\Request();
$req->merge([
    'amount' => $remainingDebt,
    'note' => 'Test Flow06D full pay',
    'mode' => 'auto',
]);
$resp = $ctrl->recordPayment($req, $ncc001->id);

$ncc001->refresh(); $pnA->refresh(); $pnB->refresh();

test("NCC debt = 0", $ncc001->supplier_debt_amount == 0, $pass, $fail, $errors, "got: {$ncc001->supplier_debt_amount}");
test("PN_A debt = 0", $pnA->debt_amount == 0, $pass, $fail, $errors, "got: {$pnA->debt_amount}");
test("PN_B debt = 0", $pnB->debt_amount == 0, $pass, $fail, $errors, "got: {$pnB->debt_amount}");

$cfCount = CashFlow::where('reference_type', 'SupplierPayment')
    ->where('target_id', $ncc001->id)->count();
test("CashFlow records >= 3", $cfCount >= 3, $pass, $fail, $errors, "got: $cfCount");

// === 06E: Chiet khau thanh toan ===
echo "\n-- 06E: Chiet khau thanh toan 100K --\n";

// Reset some debt for testing
$ncc001->update(['supplier_debt_amount' => 500000]);
$pnB->update(['debt_amount' => 500000, 'paid_amount' => 300000]);
$debtBefore06E = 500000;

$req = new \Illuminate\Http\Request();
$req->merge([
    'amount' => 100000,
    'note' => 'Test Flow06E discount',
    'type' => 'discount',
]);
$resp = $ctrl->adjustDebt($req, $ncc001->id);
$result = json_decode($resp->getContent(), true);

$ncc001->refresh();

test("API success", $result['success'] == true, $pass, $fail, $errors);
test("NCC debt giam 100K", $ncc001->supplier_debt_amount == $debtBefore06E - 100000, $pass, $fail, $errors, "got: {$ncc001->supplier_debt_amount}");

$stxDiscount = SupplierDebtTransaction::where('supplier_id', $ncc001->id)
    ->where('type', 'discount')
    ->latest()->first();
test("DebtTx type=discount", $stxDiscount !== null, $pass, $fail, $errors);
test("Discount amount = -100K", $stxDiscount && $stxDiscount->amount == -100000, $pass, $fail, $errors, "got: " . ($stxDiscount->amount ?? 'null'));

// === 06F: Dieu chinh cong no ===
echo "\n-- 06F: Dieu chinh cong no giam 50K --\n";

$ncc001->refresh();
$debtBefore06F = $ncc001->supplier_debt_amount;

$req = new \Illuminate\Http\Request();
$req->merge([
    'amount' => -50000, // negative = giam
    'note' => 'Test Flow06F adjustment',
    'type' => 'adjustment',
]);
$resp = $ctrl->adjustDebt($req, $ncc001->id);
$result = json_decode($resp->getContent(), true);

$ncc001->refresh();

test("API success", $result['success'] == true, $pass, $fail, $errors);
test("NCC debt giam 50K", $ncc001->supplier_debt_amount == $debtBefore06F - 50000, $pass, $fail, $errors, "got: {$ncc001->supplier_debt_amount}");

$stxAdj = SupplierDebtTransaction::where('supplier_id', $ncc001->id)
    ->where('type', 'adjustment')
    ->latest()->first();
test("DebtTx type=adjustment", $stxAdj !== null, $pass, $fail, $errors);
test("DebtTx co ghi chu", $stxAdj && str_contains($stxAdj->note, 'Flow06F'), $pass, $fail, $errors);

// === 06G: Lich su nhap/tra hang ===
echo "\n-- 06G: Lich su nhap/tra hang --\n";

$histResp = $ctrl->purchaseHistory($ncc001->id);
$histData = json_decode($histResp->getContent(), true);

test("History tra ve data", is_array($histData) && count($histData) > 0, $pass, $fail, $errors, "count: " . count($histData));

$histCodes = array_column($histData, 'code');
test("PN_A trong lich su", in_array($pnA->code, $histCodes), $pass, $fail, $errors);
test("PN_B trong lich su", in_array($pnB->code, $histCodes), $pass, $fail, $errors);

// === 06H: Chi nhanh ===
echo "\n-- 06H: Quan ly NCC theo chi nhanh --\n";
echo "  N/A: Khong co scope chi nhanh NCC\n";

// === 06I: Ngung/xoa NCC ===
echo "\n-- 06I: Ngung/xoa NCC --\n";
echo "  N/A: Chua co soft-delete/deactivate\n";

// === Outstanding Purchases API ===
echo "\n-- Bonus: Outstanding Purchases API --\n";

// Reset some debt for testing
$pnA->update(['debt_amount' => 100000]);
$pnB->update(['debt_amount' => 300000]);

$outResp = $ctrl->outstandingPurchases($ncc001->id);
$outData = json_decode($outResp->getContent(), true);

test("Outstanding API tra ve data", is_array($outData) && count($outData) > 0, $pass, $fail, $errors, "count: " . count($outData));
test("Co PN con no", count($outData) >= 1, $pass, $fail, $errors);

// === SUMMARY ===
echo "\n=== KET QUA: $pass PASS / $fail FAIL ===\n\n";

if (count($errors) > 0) {
    echo "DANH SACH LOI:\n";
    foreach ($errors as $i => $e) {
        echo "  " . ($i + 1) . ". $e\n";
    }
}

// === Cleanup ===
echo "\n-- Cleanup --\n";
CashFlow::where('reference_type', 'SupplierPayment')->where('target_id', $ncc001->id)->delete();
SupplierDebtTransaction::where('supplier_id', $ncc001->id)->delete();
Purchase::where('code', 'LIKE', 'PN_F06%')->each(function ($p) {
    PurchaseItem::where('purchase_id', $p->id)->delete();
    $p->delete();
});
$ncc001->update(['supplier_debt_amount' => $initDebt]);
echo "  OK Cleaned up & restored state\n";
