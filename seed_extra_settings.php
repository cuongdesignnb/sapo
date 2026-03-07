<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Setting;

$extraDefaults = [
    ['key' => 'warranty_enabled', 'value' => '1', 'group' => 'product', 'type' => 'boolean'],
    ['key' => 'production_enabled', 'value' => '1', 'group' => 'product', 'type' => 'boolean'],
    ['key' => 'supplier_by_branch', 'value' => '1', 'group' => 'partner', 'type' => 'boolean'],
    ['key' => 'user_permission_by_category', 'value' => '0', 'group' => 'user', 'type' => 'boolean'],
    ['key' => 'purchase_order_enabled', 'value' => '1', 'group' => 'purchase', 'type' => 'boolean'],
    ['key' => 'purchase_fee_enabled', 'value' => '1', 'group' => 'purchase', 'type' => 'boolean'],
    ['key' => 'transaction_allow_change_time', 'value' => '1', 'group' => 'general', 'type' => 'boolean'],
];

foreach ($extraDefaults as $s) {
    Setting::set($s['key'], $s['value'] === '1', $s['group'], $s['type']);
}

echo "Extra settings seeded successfully.";
