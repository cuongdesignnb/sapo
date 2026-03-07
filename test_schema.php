<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "has_customer_id: " . (\Illuminate\Support\Facades\Schema::hasColumn('invoices', 'customer_id') ? 'YES' : 'NO');
