<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$p = \App\Models\Product::find(71);
echo "Product #71: has_serial=" . ($p->has_serial ? 'yes' : 'no') . ", stock_qty={$p->stock_quantity}\n";

echo "\nStock Movements:\n";
$ms = \App\Models\StockMovement::where('product_id', 71)->orderBy('moved_at')->get();
foreach ($ms as $m) {
    echo "  {$m->type} | qty={$m->qty} | {$m->direction} | serial={$m->serial_imei_id} | ref={$m->ref_code}\n";
}

echo "\nPurchase Items:\n";
$pis = \DB::table('purchase_items')
    ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
    ->where('purchase_items.product_id', 71)
    ->where('purchases.status', 'completed')
    ->get(['purchase_items.quantity', 'purchases.code']);
foreach ($pis as $pi) {
    echo "  {$pi->code} | qty={$pi->quantity}\n";
}

echo "\nPurchase Return Items:\n";
$pris = \DB::table('purchase_return_items')
    ->join('purchase_returns', 'purchase_returns.id', '=', 'purchase_return_items.purchase_return_id')
    ->where('purchase_return_items.product_id', 71)
    ->get(['purchase_return_items.quantity', 'purchase_returns.code', 'purchase_returns.status']);
foreach ($pris as $pri) {
    echo "  {$pri->code} | qty={$pri->quantity} | status={$pri->status}\n";
}
