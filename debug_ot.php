<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// List all employees
$emps = \App\Models\Employee::select('id', 'name', 'code')->get();
foreach ($emps as $e) {
    echo "{$e->id} | {$e->code} | {$e->name}\n";
}
