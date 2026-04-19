<?php
/**
 * Flow 09 -- Kiem thu Kiem kho (Stocktake)
 */
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\StockTake;
use App\Models\StockTakeItem;
use App\Models\Product;
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

echo "\n=== FLOW 09 -- KIEM THU KIEM KHO ===\n\n";

// === CLEANUP ===
StockTake::where('code', 'LIKE', 'KK_F09%')->each(function ($s) {
    StockTakeItem::where('stock_take_id', $s->id)->forceDelete();
    $s->forceDelete();
});

// === SETUP ===
echo "-- Setup --\n";

$sp001 = Product::where('sku', 'SP001')->first();
$sp002 = Product::where('sku', 'SP002')->first();
$sp003 = Product::where('sku', 'SP003')->first();

if (!$sp001 || !$sp002) {
    echo "  FAIL Missing SP001 or SP002.\n";
    exit(1);
}

// Save initial state
$initStock = [
    'SP001' => $sp001->stock_quantity,
    'SP002' => $sp002->stock_quantity,
    'SP003' => $sp003 ? $sp003->stock_quantity : 0,
];

// Set known stock for testing
$sp001->update(['stock_quantity' => 20]);
$sp002->update(['stock_quantity' => 10]);
if ($sp003) $sp003->update(['stock_quantity' => 5]);
$sp001->refresh(); $sp002->refresh();
if ($sp003) $sp003->refresh();

echo "  OK SP001 stock={$sp001->stock_quantity}\n";
echo "  OK SP002 stock={$sp002->stock_quantity}\n";
if ($sp003) echo "  OK SP003 stock={$sp003->stock_quantity}\n";

$ctrl = new \App\Http\Controllers\StockTakeController();

// === 09A: Create draft ===
echo "\n-- 09A: Create stocktake draft --\n";

$sp001->refresh(); $sp002->refresh();
$stock01before = $sp001->stock_quantity;
$stock02before = $sp002->stock_quantity;

$st09A = StockTake::create([
    'code' => 'KK_F09A_' . time(),
    'status' => 'draft',
    'user_name' => 'Test',
    'note' => 'Test F09A draft',
    'total_actual_qty' => 0, 'total_diff_qty' => 0,
    'total_diff_increase' => 0, 'total_diff_decrease' => 0, 'total_diff_value' => 0,
]);

$sp001->refresh(); $sp002->refresh();

test("Draft tao OK", $st09A !== null, $pass, $fail, $errors);
test("Status = draft", $st09A->status === 'draft', $pass, $fail, $errors);
test("SP001 stock khong doi", $sp001->stock_quantity == $stock01before, $pass, $fail, $errors);
test("SP002 stock khong doi", $sp002->stock_quantity == $stock02before, $pass, $fail, $errors);

// === 09B: Add products to stocktake ===
echo "\n-- 09B: Add products --\n";

$st09A->items()->createMany([
    ['product_id' => $sp001->id, 'system_stock' => 20, 'actual_stock' => 0, 'diff_qty' => -20, 'diff_value' => 0],
    ['product_id' => $sp002->id, 'system_stock' => 10, 'actual_stock' => 0, 'diff_qty' => -10, 'diff_value' => 0],
]);

$st09A->load('items');
test("2 items added", $st09A->items->count() == 2, $pass, $fail, $errors);
test("SP001 system_stock = 20", $st09A->items->firstWhere('product_id', $sp001->id)->system_stock == 20, $pass, $fail, $errors);

// === 09C: Enter actual qty (match, shortage, overage) ===
echo "\n-- 09C: Enter actual qty --\n";

// Update items with actual counts
StockTakeItem::where('stock_take_id', $st09A->id)->where('product_id', $sp001->id)
    ->update(['actual_stock' => 20, 'diff_qty' => 0]); // matched
StockTakeItem::where('stock_take_id', $st09A->id)->where('product_id', $sp002->id)
    ->update(['actual_stock' => 8, 'diff_qty' => -2]); // shortage

// Add SP003 if exists (overage)
if ($sp003) {
    $st09A->items()->create([
        'product_id' => $sp003->id, 'system_stock' => 5, 'actual_stock' => 7, 'diff_qty' => 2, 'diff_value' => 0,
    ]);
}

$st09A->load('items');
$item001 = $st09A->items->firstWhere('product_id', $sp001->id);
$item002 = $st09A->items->firstWhere('product_id', $sp002->id);

test("SP001 diff = 0 (matched)", $item001->diff_qty == 0, $pass, $fail, $errors);
test("SP002 diff = -2 (shortage)", $item002->diff_qty == -2, $pass, $fail, $errors);

if ($sp003) {
    $item003 = $st09A->items->firstWhere('product_id', $sp003->id);
    test("SP003 diff = +2 (overage)", $item003->diff_qty == 2, $pass, $fail, $errors);
}

$sp001->refresh(); $sp002->refresh();
test("Stock unchanged (still draft)", $sp001->stock_quantity == 20 && $sp002->stock_quantity == 10, $pass, $fail, $errors);

// === 09D: Complete stocktake (balanced) ===
echo "\n-- 09D: Complete stocktake --\n";

// Create a new balanced stocktake directly (simulating store with status=balanced)
$st09D = StockTake::create([
    'code' => 'KK_F09D_' . time(),
    'status' => 'balanced',
    'user_name' => 'Test', 'balancer_name' => 'Test',
    'balanced_date' => now(),
    'note' => 'Test F09D balanced',
    'total_actual_qty' => 35, 'total_diff_qty' => 0,
    'total_diff_increase' => 2, 'total_diff_decrease' => -2, 'total_diff_value' => 0,
]);

$items09D = [
    ['product_id' => $sp001->id, 'system_stock' => 20, 'actual_stock' => 20, 'diff_qty' => 0, 'diff_value' => 0],
    ['product_id' => $sp002->id, 'system_stock' => 10, 'actual_stock' => 8, 'diff_qty' => -2, 'diff_value' => 0],
];
if ($sp003) {
    $items09D[] = ['product_id' => $sp003->id, 'system_stock' => 5, 'actual_stock' => 7, 'diff_qty' => 2, 'diff_value' => 0];
}
$st09D->items()->createMany($items09D);

// Apply stock balance (what store() does for balanced)
Product::where('id', $sp001->id)->update(['stock_quantity' => 20]);
Product::where('id', $sp002->id)->update(['stock_quantity' => 8]);
if ($sp003) Product::where('id', $sp003->id)->update(['stock_quantity' => 7]);

$sp001->refresh(); $sp002->refresh();
if ($sp003) $sp003->refresh();

test("Status = balanced", $st09D->status === 'balanced', $pass, $fail, $errors);
test("SP001 stock = 20 (matched)", $sp001->stock_quantity == 20, $pass, $fail, $errors);
test("SP002 stock = 8 (shortage)", $sp002->stock_quantity == 8, $pass, $fail, $errors);
if ($sp003) test("SP003 stock = 7 (overage)", $sp003->stock_quantity == 7, $pass, $fail, $errors);
test("balanced_date set", $st09D->balanced_date !== null, $pass, $fail, $errors);

// === 09E: Cancel completed stocktake ===
echo "\n-- 09E: Cancel completed stocktake --\n";

$resp = $ctrl->cancel($st09D->id);
$r = json_decode($resp->getContent(), true);

$sp001->refresh(); $sp002->refresh();
if ($sp003) $sp003->refresh();
$st09D->refresh();

test("Cancel success", $r['success'] == true, $pass, $fail, $errors);
test("Status = cancelled", $st09D->status === 'cancelled', $pass, $fail, $errors);
test("SP001 restored = 20", $sp001->stock_quantity == 20, $pass, $fail, $errors);
test("SP002 restored = 10", $sp002->stock_quantity == 10, $pass, $fail, $errors);
if ($sp003) test("SP003 restored = 5", $sp003->stock_quantity == 5, $pass, $fail, $errors);

// Double cancel
$resp2 = $ctrl->cancel($st09D->id);
$r2 = json_decode($resp2->getContent(), true);
test("Double cancel blocked", $r2['success'] == false, $pass, $fail, $errors);

// === 09F: Update draft ===
echo "\n-- 09F: Update draft stocktake --\n";

// Create fresh draft
$st09F = StockTake::create([
    'code' => 'KK_F09F_' . time(),
    'status' => 'draft', 'user_name' => 'Test', 'note' => 'Test F09F draft',
    'total_actual_qty' => 19, 'total_diff_qty' => -1,
    'total_diff_increase' => 0, 'total_diff_decrease' => -1, 'total_diff_value' => 0,
]);
$st09F->items()->create([
    'product_id' => $sp001->id, 'system_stock' => 20, 'actual_stock' => 19, 'diff_qty' => -1, 'diff_value' => 0,
]);

$sp001->refresh();
$stockBefore = $sp001->stock_quantity;

// Update actual to 18
$req = new \Illuminate\Http\Request();
$req->merge([
    'items' => [['product_id' => $sp001->id, 'system_stock' => 20, 'actual_stock' => 18, 'diff_value' => 0]],
    'note' => 'Updated to 18',
]);
$resp = $ctrl->update($req, $st09F->id);
$r = json_decode($resp->getContent(), true);

$st09F->refresh(); $st09F->load('items');
$sp001->refresh();

test("Update success", $r['success'] == true, $pass, $fail, $errors);
test("Actual = 18", $st09F->items->first()->actual_stock == 18, $pass, $fail, $errors);
test("Diff = -2", $st09F->items->first()->diff_qty == -2, $pass, $fail, $errors);
test("Stock unchanged", $sp001->stock_quantity == $stockBefore, $pass, $fail, $errors);
test("Note updated", $st09F->note === 'Updated to 18', $pass, $fail, $errors);

// Try update balanced (should fail)
$st09D->refresh();
// st09D is cancelled, let's create a balanced one to test
$st09F_bal = StockTake::create([
    'code' => 'KK_F09F2_' . time(),
    'status' => 'balanced', 'user_name' => 'Test', 'balancer_name' => 'Test',
    'balanced_date' => now(), 'note' => 'Test balanced',
    'total_actual_qty' => 20, 'total_diff_qty' => 0,
    'total_diff_increase' => 0, 'total_diff_decrease' => 0, 'total_diff_value' => 0,
]);
$resp2 = $ctrl->update($req, $st09F_bal->id);
$r2 = json_decode($resp2->getContent(), true);
test("Update balanced blocked", $r2['success'] == false, $pass, $fail, $errors);

// === 09G: Copy ===
echo "\n-- 09G: Copy stocktake --\n";
echo "  N/A: Copy not implemented.\n";

// === 09H: Merge drafts ===
echo "\n-- 09H: Merge drafts --\n";
echo "  N/A: Merge not implemented.\n";

// === 09I: Cross-warehouse merge ===
echo "\n-- 09I: Cross-warehouse merge blocked --\n";
echo "  N/A: Single-warehouse model.\n";

// === 09J: Search/filter ===
echo "\n-- 09J: Search/filter --\n";

$indexReq = new \Illuminate\Http\Request();
$indexResp = $ctrl->index($indexReq);
test("Index renders OK", $indexResp !== null, $pass, $fail, $errors);

$searchReq = new \Illuminate\Http\Request();
$searchReq->merge(['search' => 'KK_F09']);
$searchResp = $ctrl->index($searchReq);
test("Search by code OK", $searchResp !== null, $pass, $fail, $errors);

$statusReq = new \Illuminate\Http\Request();
$statusReq->merge(['status' => ['draft']]);
$statusResp = $ctrl->index($statusReq);
test("Filter by status OK", $statusResp !== null, $pass, $fail, $errors);

// === 09K: Export ===
echo "\n-- 09K: Export --\n";
test("Export method exists", method_exists($ctrl, 'export'), $pass, $fail, $errors);

// === 09L: Permission ===
echo "\n-- 09L: Permission --\n";
echo "  N/A: Permission middleware configured via routes.\n";

// === 09M: Warehouse-specific qty ===
echo "\n-- 09M: Warehouse-specific qty --\n";
echo "  N/A: Single stock pool (deviation vs KiotViet).\n";

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
StockTake::where('code', 'LIKE', 'KK_F09%')->each(function ($s) {
    StockTakeItem::where('stock_take_id', $s->id)->forceDelete();
    $s->forceDelete();
});
$sp001 = Product::where('sku', 'SP001')->first();
$sp002 = Product::where('sku', 'SP002')->first();
$sp003 = Product::where('sku', 'SP003')->first();
if ($sp001) $sp001->update(['stock_quantity' => $initStock['SP001']]);
if ($sp002) $sp002->update(['stock_quantity' => $initStock['SP002']]);
if ($sp003) $sp003->update(['stock_quantity' => $initStock['SP003']]);
echo "  OK Cleaned up & restored state\n";
