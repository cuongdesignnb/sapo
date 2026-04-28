<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$pid = 71;
echo "=== ALL SOURCES for product_id={$pid} ===\n\n";

echo "1. Purchase Items:\n";
$pis = DB::table('purchase_items')
    ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
    ->where('purchase_items.product_id', $pid)
    ->where('purchases.status', 'completed')
    ->get(['purchase_items.quantity', 'purchase_items.price', 'purchase_items.unit_cost_allocated', 'purchases.code', 'purchases.created_at']);
foreach ($pis as $p) echo "  {$p->code} qty={$p->quantity} price={$p->price} allocated={$p->unit_cost_allocated} date={$p->created_at}\n";

echo "\n2. Invoice Items:\n";
$iis = DB::table('invoice_items')
    ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
    ->where('invoice_items.product_id', $pid)
    ->where(function ($q) {
        $q->whereNull('invoices.status')->orWhere('invoices.status', '!=', 'cancelled');
    })
    ->get(['invoice_items.quantity', 'invoices.code', 'invoices.status', 'invoices.created_at']);
foreach ($iis as $i) echo "  {$i->code} qty={$i->quantity} status={$i->status} date={$i->created_at}\n";

echo "\n3. Return Items:\n";
$ris = DB::table('return_items')
    ->join('returns', 'returns.id', '=', 'return_items.return_id')
    ->where('return_items.product_id', $pid)
    ->where(function ($q) {
        $q->where('returns.status', '!=', 'Đã hủy')->orWhereNull('returns.status');
    })
    ->get(['return_items.quantity', 'returns.code', 'returns.status', 'returns.created_at']);
foreach ($ris as $r) echo "  {$r->code} qty={$r->quantity} status={$r->status} date={$r->created_at}\n";

echo "\n4. Purchase Return Items:\n";
$pris = DB::table('purchase_return_items')
    ->join('purchase_returns', 'purchase_returns.id', '=', 'purchase_return_items.purchase_return_id')
    ->where('purchase_return_items.product_id', $pid)
    ->where('purchase_returns.status', 'completed')
    ->get(['purchase_return_items.quantity', 'purchase_return_items.price', 'purchase_return_items.cost_price', 'purchase_returns.code', 'purchase_returns.created_at']);
foreach ($pris as $pr) echo "  {$pr->code} qty={$pr->quantity} price={$pr->price} cost_price={$pr->cost_price} date={$pr->created_at}\n";

echo "\nExpected: 4 purchase - 2 purchase_return = 2\n";
