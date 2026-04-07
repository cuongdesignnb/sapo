<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// List all employees
echo "=== ALL EMPLOYEES ===\n";
foreach (App\Models\Employee::all() as $e) {
    echo "  {$e->id} - {$e->name}\n";
}
echo "\n";

// List all salary settings with custom_deductions
echo "=== SALARY SETTINGS WITH DEDUCTIONS ===\n";
foreach (App\Models\EmployeeSalarySetting::whereNotNull('custom_deductions')->get() as $s) {
    $empName = $s->employee ? $s->employee->name : 'N/A';
    echo "Employee {$s->employee_id} ({$empName}):\n";
    echo "  has_deduction: " . ($s->has_deduction ? 'true' : 'false') . "\n";
    echo "  custom_deductions: " . json_encode($s->custom_deductions, JSON_UNESCAPED_UNICODE) . "\n\n";
}

// Also check PayrollSetting
$ps = App\Models\PayrollSetting::first();
echo "PayrollSetting late_penalty_enabled: " . ($ps->late_penalty_enabled ? 'true' : 'false') . "\n";
echo "PayrollSetting late_penalty_tiers:\n";
echo json_encode($ps->late_penalty_tiers, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

// If template exists, check template deductions
if ($s->salary_template_id) {
    $tmpl = App\Models\SalaryTemplate::find($s->salary_template_id);
    if ($tmpl) {
        echo "Template: {$tmpl->name}\n";
        echo "Template deductions: " . json_encode($tmpl->deductions, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    }
}
