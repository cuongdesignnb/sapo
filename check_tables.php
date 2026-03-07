<?php
error_reporting(0);
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// All cash_flows
echo "All cash_flows (" . \DB::table('cash_flows')->count() . " total):\n";
$all = \DB::table('cash_flows')->limit(10)->get();
foreach ($all as $s) {
    echo "  code={$s->code} type={$s->type} amt={$s->amount} target={$s->target_type}:{$s->target_id} ref={$s->reference_type}:{$s->reference_code}\n";
}

// Sample invoices with customer
echo "\nInvoices with customer_id set:\n";
$invs = \DB::table('invoices')->whereNotNull('customer_id')->limit(5)->get(['id','code','total','customer_paid','customer_id','created_at','status']);
foreach ($invs as $i) {
    echo "  id={$i->id} code={$i->code} total={$i->total} paid={$i->customer_paid} cust={$i->customer_id} status={$i->status}\n";
}

// Order returns table
echo "\norder_returns exists: " . (\Schema::hasTable('order_returns') ? 'yes' : 'no') . "\n";
if (\Schema::hasTable('order_returns')) {
    echo "  cols: " . implode(', ', \Schema::getColumnListing('order_returns')) . "\n";
    echo "  count: " . \DB::table('order_returns')->count() . "\n";
}
