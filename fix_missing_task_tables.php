<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $driver = DB::connection()->getDriverName();
    if ($driver !== 'mysql') {
        echo "This script is designed for the MySQL server database.\n";
    }

    $migrationsToDelete = [
        '2026_03_11_000002_create_device_repairs_table',
        '2026_03_11_000003_create_device_repair_parts_table',
        '2026_03_11_000004_create_repair_performance_tiers_table',
        '2026_03_11_000005_seed_repair_settings',
        '2026_03_11_000006_add_deadline_to_device_repairs_table',
        '2026_03_15_000003_create_task_categories_table',
        '2026_03_15_000004_upgrade_device_repairs_to_tasks',
        '2026_03_15_000005_create_task_assignments_table',
        '2026_03_15_000006_create_task_comments_table',
        '2026_03_15_000007_seed_task_module_setting'
    ];

    echo "Checking migration records to delete:\n";
    $found = DB::table('migrations')
        ->whereIn('migration', $migrationsToDelete)
        ->pluck('migration')
        ->toArray();

    if (empty($found)) {
        echo "No matching migration records found in the 'migrations' table.\n";
    } else {
        echo "Found the following records: \n";
        foreach ($found as $m) {
            echo " - {$m}\n";
        }
        
        echo "\nDeleting these records from 'migrations' table...\n";
        $deleted = DB::table('migrations')
            ->whereIn('migration', $migrationsToDelete)
            ->delete();
            
        echo "Deleted {$deleted} records successfully!\n";
    }

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
