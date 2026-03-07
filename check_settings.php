<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $exists = Schema::hasTable('settings');
    echo $exists ? "TABLE EXISTS\n" : "TABLE MISSING\n";
    if ($exists) {
        $count = DB::table('settings')->count();
        echo "COUNT: $count\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
