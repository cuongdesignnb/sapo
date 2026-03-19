# Cleanup script - remove all PC/laptop repair related files

# Models
$files = @(
    'app\Models\Task.php',
    'app\Models\TaskAssignment.php',
    'app\Models\TaskCategory.php',
    'app\Models\TaskComment.php',
    'app\Models\TaskPart.php',
    'app\Models\DeviceRepair.php',
    'app\Models\DeviceRepairPart.php',
    'app\Models\RepairPerformanceTier.php',
    'app\Models\Warranty.php',
    'app\Services\RepairService.php',
    'app\Services\TaskService.php',
    'app\Http\Controllers\TaskPageController.php',
    'app\Http\Controllers\DeviceRepairPageController.php',
    'app\Http\Controllers\WarrantyController.php',
    'app\Http\Controllers\Api\TaskController.php',
    'app\Http\Controllers\Api\DeviceRepairController.php',
    'app\Http\Controllers\Api\RepairPerformanceTierController.php',
    'app\Http\Controllers\Api\MyTasksController.php',
    'app\Notifications\TaskCommentNotification.php',
    'app\Console\Commands\SyncRepairCostPrice.php',
    'repair_settings.php',
    'database\migrations\2026_03_11_000001_add_repair_fields_to_serial_imeis_table.php',
    'database\migrations\2026_03_11_000002_create_device_repairs_table.php',
    'database\migrations\2026_03_11_000003_create_device_repair_parts_table.php',
    'database\migrations\2026_03_11_000004_create_repair_performance_tiers_table.php',
    'database\migrations\2026_03_11_000005_seed_repair_settings.php',
    'database\migrations\2026_03_11_000006_add_deadline_to_device_repairs_table.php',
    'database\migrations\2026_03_15_000003_create_task_categories_table.php',
    'database\migrations\2026_03_15_000004_upgrade_device_repairs_to_tasks.php',
    'database\migrations\2026_03_15_000005_create_task_assignments_table.php',
    'database\migrations\2026_03_15_000006_create_task_comments_table.php',
    'database\migrations\2026_03_15_000007_seed_task_module_setting.php'
)

foreach ($f in $files) {
    if (Test-Path $f) {
        Remove-Item -Force $f
        Write-Host "Deleted: $f"
    }
}

# Directories
$dirs = @(
    'resources\js\Pages\Tasks',
    'resources\js\Pages\Repairs',
    'resources\js\Pages\Warranties'
)

foreach ($d in $dirs) {
    if (Test-Path $d) {
        Remove-Item -Recurse -Force $d
        Write-Host "Deleted dir: $d"
    }
}

Write-Host "`nCleanup complete!"
