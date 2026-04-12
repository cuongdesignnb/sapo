<?php
/**
 * DIAGNOSTIC & REPAIR SCRIPT
 * Kiểm tra và sửa dữ liệu POS "mồ côi" (serial sold nhưng không có invoice)
 * 
 * Chạy: php diagnose_pos.php
 * Sửa:  php diagnose_pos.php --fix
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$fix = in_array('--fix', $argv ?? []);

echo "╔══════════════════════════════════════════════════╗\n";
echo "║  DIAGNOSTIC: POS Orphaned Data Check             ║\n";
echo "╚══════════════════════════════════════════════════╝\n\n";

// 1) Serials marked as 'sold' but invoice doesn't exist
echo "=== 1. SERIAL ORPHANED (sold but no Invoice) ===\n";
$orphanedSerials = \App\Models\SerialImei::where('status', 'sold')
    ->where(function($q) {
        $q->whereNull('invoice_id')
          ->orWhereNotIn('invoice_id', \App\Models\Invoice::pluck('id'));
    })
    ->get();

if ($orphanedSerials->isEmpty()) {
    echo "  ✅ Không có serial mồ côi nào.\n";
} else {
    echo "  ⚠️  Tìm thấy " . $orphanedSerials->count() . " serial mồ côi:\n";
    foreach ($orphanedSerials as $s) {
        $productName = $s->product ? $s->product->name : "Product #{$s->product_id}";
        echo "  - Serial: {$s->serial_number} | Product: {$productName} | Invoice ID: " . ($s->invoice_id ?? 'NULL') . " | Sold at: {$s->sold_at}\n";
        
        if ($fix) {
            $s->update([
                'status' => 'in_stock',
                'sold_at' => null,
                'invoice_id' => null,
            ]);
            echo "    → ĐÃ KHÔI PHỤC về in_stock ✅\n";
        }
    }
}

// 2) Products with negative stock
echo "\n═══ 2. SẢN PHẨM TỒN KHO ÂM ═══\n";
$negativeStock = \App\Models\Product::where('stock_quantity', '<', 0)->get();
if ($negativeStock->isEmpty()) {
    echo "  ✅ Không có sản phẩm tồn kho âm.\n";
} else {
    echo "  ⚠️  Tìm thấy " . $negativeStock->count() . " sản phẩm tồn kho âm:\n";
    foreach ($negativeStock as $p) {
        echo "  - {$p->sku} | {$p->name} | Stock: {$p->stock_quantity}\n";
    }
}

// 3) Stock mismatch: products with serials where serial count != stock_quantity
echo "\n═══ 3. KIỂM TRA TỒN KHO VS SERIAL ═══\n";
$serialProducts = \App\Models\Product::where('has_serial', true)->get();
$mismatchCount = 0;
foreach ($serialProducts as $p) {
    $inStockSerials = \App\Models\SerialImei::where('product_id', $p->id)
        ->where('status', 'in_stock')
        ->count();
    $soldSerials = \App\Models\SerialImei::where('product_id', $p->id)
        ->where('status', 'sold')
        ->count();
    $totalSerials = \App\Models\SerialImei::where('product_id', $p->id)->count();
    
    if ($inStockSerials != $p->stock_quantity) {
        $mismatchCount++;
        echo "  ⚠️  {$p->sku} | {$p->name}\n";
        echo "     DB stock_quantity: {$p->stock_quantity} | Serial in_stock: {$inStockSerials} | Serial sold: {$soldSerials} | Total: {$totalSerials}\n";
        
        if ($fix) {
            $p->update(['stock_quantity' => $inStockSerials]);
            echo "     → ĐÃ SỬA stock → {$inStockSerials} ✅\n";
        }
    }
}
if ($mismatchCount === 0) {
    echo "  ✅ Tất cả sản phẩm serial khớp tồn kho.\n";
}

// 4) Invoices without items (ghost invoices) 
echo "\n═══ 4. HÓA ĐƠN GHOST (không có items) ═══\n";
$ghostInvoices = \App\Models\Invoice::doesntHave('items')->get();
if ($ghostInvoices->isEmpty()) {
    echo "  ✅ Không có hóa đơn ghost.\n";
} else {
    echo "  ⚠️  Tìm thấy " . $ghostInvoices->count() . " hóa đơn không có items:\n";
    foreach ($ghostInvoices as $inv) {
        echo "  - {$inv->code} | Total: " . number_format($inv->total) . " | {$inv->created_at}\n";
        if ($fix) {
            // Don't auto-delete invoices, just report
            echo "    → Cần xóa thủ công nếu là ghost data\n";
        }
    }
}

// 5) Customer debt consistency check
echo "\n=== 5. CONG NO KHACH HANG ===\n";
$debtCustomers = \App\Models\Customer::where('debt_amount', '!=', 0)->get();
echo "  Khach co no: " . $debtCustomers->count() . "\n";
foreach ($debtCustomers as $c) {
    try {
        $totalInvoiced = \App\Models\Invoice::where('customer_id', $c->id)->sum('total');
        $totalPaid = \App\Models\Invoice::where('customer_id', $c->id)->sum('customer_paid');
        
        // Try refund_amount, fallback to total_refund or 0
        $totalReturned = 0;
        try {
            $totalReturned = \DB::table('returns')->where('customer_id', $c->id)->sum('refund_amount');
        } catch (\Exception $e) {
            try {
                $totalReturned = \DB::table('returns')->where('customer_id', $c->id)->sum('total_refund');
            } catch (\Exception $e2) {
                // No returns table or column, skip
            }
        }
        
        $debtPayments = \App\Models\CashFlow::where('target_id', $c->id)
            ->where('target_type', 'Khách hàng')
            ->where('category', 'like', '%Thanh toán%')
            ->sum('amount');
        
        $expectedDebt = $totalInvoiced - $totalPaid - $debtPayments - $totalReturned;
        
        if (abs($expectedDebt - $c->debt_amount) > 1) {
            echo "  !! {$c->code} | {$c->name} | DB debt: " . number_format($c->debt_amount) . " | Calculated: " . number_format($expectedDebt) . "\n";
        }
    } catch (\Exception $e) {
        echo "  !! Error checking {$c->code}: " . $e->getMessage() . "\n";
    }
}

// 6) Summary
echo "\n═══ SUMMARY ═══\n";
echo "  Invoices: " . \App\Models\Invoice::count() . "\n";
echo "  Orders: " . \App\Models\Order::count() . "\n";
echo "  CashFlows: " . \App\Models\CashFlow::count() . "\n";
echo "  Serials (total): " . \App\Models\SerialImei::count() . "\n";
echo "  Serials (sold): " . \App\Models\SerialImei::where('status', 'sold')->count() . "\n";
echo "  Serials (in_stock): " . \App\Models\SerialImei::where('status', 'in_stock')->count() . "\n";

if (!$fix) {
    echo "\n💡 Chạy lại với --fix để tự động sửa: php diagnose_pos.php --fix\n";
} else {
    echo "\n✅ Đã sửa xong!\n";
}
