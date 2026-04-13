<?php
/**
 * DIAGNOSTIC 2: Kiểm tra assignments thuộc employee nào, 
 * và employee nào có user login
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== TASK ASSIGNMENTS BY EMPLOYEE ===\n";
$byEmployee = DB::table('task_assignments')
    ->select('employee_id', DB::raw('count(*) as total'),
        DB::raw("SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending_cnt"),
        DB::raw("SUM(CASE WHEN status='accepted' THEN 1 ELSE 0 END) as accepted_cnt"))
    ->groupBy('employee_id')
    ->get();

foreach ($byEmployee as $b) {
    $emp = DB::table('employees')->where('id', $b->employee_id)->first();
    $user = ($emp && $emp->user_id) ? DB::table('users')->where('id', $emp->user_id)->first() : null;
    $loginInfo = $user ? "✅ {$user->email}" : "❌ NO LOGIN";
    echo "EMP#{$b->employee_id} " . ($emp ? $emp->name : '??') . " | total={$b->total} pending={$b->pending_cnt} accepted={$b->accepted_cnt} | {$loginInfo}\n";
}

echo "\n=== TASKS BY assigned_employee_id (old field) ===\n";
$byOld = DB::table('tasks')
    ->select('assigned_employee_id', DB::raw('count(*) as cnt'))
    ->whereNotNull('assigned_employee_id')
    ->groupBy('assigned_employee_id')
    ->get();

foreach ($byOld as $b) {
    $emp = DB::table('employees')->where('id', $b->assigned_employee_id)->first();
    echo "EMP#{$b->assigned_employee_id} " . ($emp ? $emp->name : '??') . " | {$b->cnt} tasks (old field)\n";
}

// Kiểm tra user đang login là nhân viên nào
echo "\n=== USERS WITH EMPLOYEE LINK ===\n";
$users = DB::table('users')->get();
foreach ($users as $u) {
    $emp = DB::table('employees')->where('user_id', $u->id)->first();
    echo "USER#{$u->id} {$u->email} ({$u->name}) → " . ($emp ? "EMP#{$emp->id} {$emp->name}" : "❌ NO EMP") . "\n";
}

// Test: what would MyTasksController return for user#9 (Nguyễn Xuân Thành)?
echo "\n=== SIMULATING /api/my-tasks for each linked user ===\n";
$linkedEmployees = DB::table('employees')->whereNotNull('user_id')->where('is_active', true)->get();
foreach ($linkedEmployees as $emp) {
    $taskCount = DB::table('task_assignments')
        ->where('employee_id', $emp->id)
        ->count();
    $tasks = DB::table('task_assignments')
        ->join('tasks', 'tasks.id', '=', 'task_assignments.task_id')
        ->where('task_assignments.employee_id', $emp->id)
        ->select('tasks.code', 'tasks.status', 'task_assignments.status as assign_status')
        ->orderByDesc('tasks.id')
        ->limit(3)
        ->get();
    
    echo "\nEMP#{$emp->id} {$emp->name} (user_id={$emp->user_id}): {$taskCount} assignments\n";
    foreach ($tasks as $t) {
        echo "  {$t->code} | task={$t->status} | assign={$t->assign_status}\n";
    }
}
