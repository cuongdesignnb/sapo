<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

try {
    echo "Checking settings table...\n";
    if (Schema::hasTable('settings')) {
        echo "Table exists!\n";
        print_r(DB::table('settings')->get()->toArray());
    } else {
        echo "Table MISSING. Attempting to run migration manually...\n";
        // Manual migration logic if artisan fails
        Schema::create('settings', function ($table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general');
            $table->string('type')->default('string');
            $table->timestamps();
        });
        echo "Table created manually.\n";

        $defaults = [
            ['key' => 'product_barcode_auto', 'value' => '1', 'group' => 'product', 'type' => 'boolean'],
            ['key' => 'product_suggest_info', 'value' => '1', 'group' => 'product', 'type' => 'boolean'],
            ['key' => 'product_use_serial', 'value' => '1', 'group' => 'product', 'type' => 'boolean'],
            ['key' => 'product_multiple_units', 'value' => '1', 'group' => 'product', 'type' => 'boolean'],
            ['key' => 'inventory_costing_method', 'value' => 'average', 'group' => 'inventory', 'type' => 'string'],
            ['key' => 'inventory_allow_oversell', 'value' => '0', 'group' => 'inventory', 'type' => 'boolean'],
            ['key' => 'inventory_check_by_branch', 'value' => '1', 'group' => 'inventory', 'type' => 'boolean'],
            ['key' => 'order_allow_change_time', 'value' => '1', 'group' => 'order', 'type' => 'boolean'],
        ];

        foreach ($defaults as $setting) {
            DB::table('settings')->insert(array_merge($setting, ['created_at' => now(), 'updated_at' => now()]));
        }
        echo "Defaults seeded.\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
