<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    $driver = DB::connection()->getDriverName();
    $dbName = DB::connection()->getDatabaseName();
    echo "Connection Driver: " . $driver . "\n";
    echo "Database Name: " . $dbName . "\n\n";

    echo "Checking tables:\n";
    $tablesToCheck = [
        'tasks',
        'device_repairs',
        'task_parts',
        'device_repair_parts',
        'task_assignments',
        'task_comments',
        'task_categories',
        'users',
        'customers',
        'invoices'
    ];

    foreach ($tablesToCheck as $table) {
        $exists = Schema::hasTable($table) ? "YES" : "NO";
        echo "- Table '{$table}': {$exists}\n";
    }

    echo "\nAll tables in database:\n";
    if ($driver === 'mysql') {
        $tables = DB::select('SHOW TABLES');
        $key = 'Tables_in_' . $dbName;
        foreach ($tables as $t) {
            echo "  " . $t->$key . "\n";
        }
    } else {
        $tables = Schema::getConnection()->getDoctrineSchemaManager()->listTableNames();
        foreach ($tables as $t) {
            echo "  " . $t . "\n";
        }
    }

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
