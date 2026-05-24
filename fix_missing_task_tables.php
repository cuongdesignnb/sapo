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

    echo "1. Forcing drop of potentially orphaned or ghost tables on MySQL...\n";
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    
    $tablesToDrop = [
        'task_assignments',
        'task_comments',
        'task_parts',
        'device_repair_parts',
        'tasks',
        'device_repairs',
        'repair_performance_tiers',
        'task_categories'
    ];
    
    foreach ($tablesToDrop as $table) {
        DB::statement("DROP TABLE IF EXISTS {$table};");
        echo " - Dropped table {$table} (if it existed)\n";
    }
    
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    echo "Orphaned tables drop command executed.\n";

    echo "\n2. Scanning database/migrations folder to find all tasks/repairs migrations...\n";
    $migrationsToDelete = [];
    $dir = __DIR__ . '/database/migrations';
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $name = pathinfo($file, PATHINFO_FILENAME);
                if (str_contains($name, 'task') || str_contains($name, 'repair') || str_contains($name, 'performance_tier')) {
                    $migrationsToDelete[] = $name;
                }
            }
        }
    }

    echo "Found " . count($migrationsToDelete) . " migrations to clear.\n";
    
    echo "\n3. Deleting these records from 'migrations' table...\n";
    $deleted = DB::table('migrations')
        ->whereIn('migration', $migrationsToDelete)
        ->delete();
        
    echo "Deleted {$deleted} records successfully!\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
