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

    echo "Checking conflicting foreign key constraints in database:\n";
    $constraints = DB::select("
        SELECT CONSTRAINT_NAME, TABLE_NAME 
        FROM information_schema.TABLE_CONSTRAINTS 
        WHERE CONSTRAINT_SCHEMA = DATABASE() 
          AND CONSTRAINT_NAME LIKE '%device_repairs%'
    ");

    if (empty($constraints)) {
        echo "No conflicting constraints containing 'device_repairs' found.\n";
    } else {
        echo "Found conflicting constraints:\n";
        foreach ($constraints as $c) {
            echo " - Constraint: {$c->CONSTRAINT_NAME} on Table: {$c->TABLE_NAME}\n";
        }
    }

    echo "\nForcing drop of potentially orphaned or ghost tables on MySQL...\n";
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    DB::statement('DROP TABLE IF EXISTS task_assignments;');
    DB::statement('DROP TABLE IF EXISTS task_comments;');
    DB::statement('DROP TABLE IF EXISTS task_parts;');
    DB::statement('DROP TABLE IF EXISTS device_repair_parts;');
    DB::statement('DROP TABLE IF EXISTS tasks;');
    DB::statement('DROP TABLE IF EXISTS device_repairs;');
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    echo "Orphaned tables drop command executed.\n";

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
        '2026_03_15_000007_seed_task_module_setting',
        '2026_03_19_221000_add_direction_to_task_parts_table'
    ];

    echo "\nChecking migration records to delete:\n";
    $found = DB::table('migrations')
        ->whereIn('migration', $migrationsToDelete)
        ->pluck('migration')
        ->toArray();

    if (empty($found)) {
        echo "No matching migration records found in the 'migrations' table.\n";
    } else {
        echo "Deleting these records from 'migrations' table...\n";
        $deleted = DB::table('migrations')
            ->whereIn('migration', $migrationsToDelete)
            ->delete();
            
        echo "Deleted {$deleted} records successfully!\n";
    }

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
