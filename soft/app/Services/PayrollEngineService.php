<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeCommission;
use App\Models\EmployeeSalaryConfig;
use App\Models\PayrollSheet;
use App\Models\PayrollSheetItem;
use App\Models\SalaryTemplateItem;
use App\Models\TimekeepingRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollEngineService
{
    public function __construct(
        private readonly TimekeepingService $timekeepingService,
    ) {
    }

    /**
     * Generate (or regenerate) payroll sheet for a period.
     *
     * Idempotent: re-running for same period replaces items.
     */
    public function generateSheet(
        Carbon $periodStart,
        Carbon $periodEnd,
        bool $recalculateTimekeeping = true,
        ?int $generatedBy = null,
        ?string $payCycle = 'monthly',
        ?array $employeeIds = null
    ): PayrollSheet
    {
        $periodStart = $periodStart->copy()->startOfDay();
        $periodEnd = $periodEnd->copy()->endOfDay();

        if ($periodEnd->lessThan($periodStart)) {
            throw new \InvalidArgumentException('period_end must be >= period_start');
        }

        if ($recalculateTimekeeping) {
            if (is_array($employeeIds) && count($employeeIds)) {
                foreach (array_values(array_unique($employeeIds)) as $employeeId) {
                    $this->timekeepingService->recalculateForRange($periodStart, $periodEnd, (int) $employeeId);
                }
            } else {
                $this->timekeepingService->recalculateForRange($periodStart, $periodEnd);
            }
        }

        return DB::transaction(function () use ($periodStart, $periodEnd, $generatedBy, $payCycle, $employeeIds) {
            $sheet = PayrollSheet::query()->firstOrCreate(
                [
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                ],
                [
                    'status' => 'draft',
                ]
            );

            if ($sheet->status !== 'draft') {
                throw new \RuntimeException('Payroll sheet is not draft; unlock it before regenerating.');
            }

            PayrollSheetItem::query()->where('payroll_sheet_id', $sheet->id)->delete();

            // Best-effort assign code/name
            if (!$sheet->code) {
                $sheet->forceFill([
                    'code' => 'BL' . str_pad((string) $sheet->id, 6, '0', STR_PAD_LEFT),
                ])->save();
            }
            if (!$sheet->name) {
                $month = $sheet->period_end?->format('m/Y');
                $sheet->forceFill([
                    'name' => $month ? ('Bảng lương tháng ' . $month) : null,
                ])->save();
            }

            if ($payCycle && $sheet->pay_cycle !== $payCycle) {
                $sheet->forceFill(['pay_cycle' => $payCycle])->save();
            }

            $employeesQuery = Employee::query()
                ->where('status', 'active')
                ->with(['salaryConfig.template.items'])
                ->orderBy('id');

            if (is_array($employeeIds) && count($employeeIds)) {
                $employeesQuery->whereIn('id', array_values(array_unique(array_map('intval', $employeeIds))));
            }

            $employees = $employeesQuery->get();

            foreach ($employees as $employee) {
                /** @var EmployeeSalaryConfig|null $config */
                $config = $employee->salaryConfig;
                if (!$config || $config->status !== 'active') {
                    continue;
                }

                $template = $config->template;
                if (!$template || $template->status !== 'active') {
                    continue;
                }

                $baseSalary = $config->base_salary_override ?? $template->base_salary;
                $standardUnits = (float) ($template->standard_work_units ?? 26);
                $halfDayHours = (float) ($template->half_day_threshold_hours ?? 4.5);
                $overtimeRate = $config->overtime_hourly_rate_override ?? $template->overtime_hourly_rate;

                $records = TimekeepingRecord::query()
                    ->where('employee_id', $employee->id)
                    ->whereBetween('work_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                    ->orderBy('work_date')
                    ->get();

                $daily = [];
                $workedUnits = 0.0;
                $salaryFromUnits = 0.0;
                $otMinutes = 0;

                foreach ($records as $r) {
                    $unit = 0.0;
                    if ($r->worked_minutes > 0) {
                        $unit = ($r->worked_minutes >= (int) round($halfDayHours * 60)) ? 1.0 : 0.5;
                    }

                    $multiplier = (float) ($r->holiday_multiplier ?? 1);
                    $daySalary = $standardUnits > 0 ? ((float) $baseSalary / $standardUnits) * $unit * $multiplier : 0;

                    $workedUnits += $unit;
                    $salaryFromUnits += $daySalary;
                    $otMinutes += (int) $r->ot_minutes;

                    $daily[] = [
                        'date' => $r->work_date?->toDateString(),
                        'worked_minutes' => $r->worked_minutes,
                        'unit' => $unit,
                        'holiday_multiplier' => $multiplier,
                        'day_salary' => $daySalary,
                        'late_minutes' => $r->late_minutes,
                        'early_minutes' => $r->early_minutes,
                        'ot_minutes' => $r->ot_minutes,
                    ];
                }

                $allowances = 0.0;
                $deductions = 0.0;
                foreach ($template->items as $item) {
                    if (($item->status ?? 'active') !== 'active') {
                        continue;
                    }

                    $amount = (float) $item->amount;
                    if (in_array($item->type, ['allowance', 'bonus'], true)) {
                        $allowances += $amount;
                    }
                    if (in_array($item->type, ['deduction', 'penalty'], true)) {
                        $deductions += $amount;
                    }
                }

                $commissionTotal = (float) EmployeeCommission::query()
                    ->where('employee_id', $employee->id)
                    ->whereBetween('earned_at', [$periodStart->toDateString(), $periodEnd->toDateString()])
                    ->sum('commission_amount');

                $otHours = $otMinutes / 60.0;
                $otPay = $otHours * (float) ($overtimeRate ?? 0);

                $gross = $salaryFromUnits + $otPay + $allowances + $commissionTotal;
                $net = $gross - $deductions;

                $createdItem = PayrollSheetItem::create([
                    'payroll_sheet_id' => $sheet->id,
                    'employee_id' => $employee->id,
                    'warehouse_id' => $config->pay_warehouse_id,
                    'base_salary' => $baseSalary,
                    'standard_work_units' => $standardUnits,
                    'worked_units' => $workedUnits,
                    'overtime_minutes' => $otMinutes,
                    'overtime_pay' => $otPay,
                    'allowances' => $allowances,
                    'deductions' => $deductions,
                    'commissions' => $commissionTotal,
                    'gross_salary' => $gross,
                    'net_salary' => $net,
                    'paid_amount' => 0,
                    'breakdown' => [
                        'template_id' => $template->id,
                        'template_name' => $template->name,
                        'half_day_threshold_hours' => $halfDayHours,
                        'overtime_hourly_rate' => $overtimeRate,
                        'daily' => $daily,
                    ],
                ]);

                if ($createdItem && !$createdItem->code) {
                    $createdItem->forceFill([
                        'code' => 'PL' . str_pad((string) $createdItem->id, 6, '0', STR_PAD_LEFT),
                    ])->save();
                }
            }

            $sheet->forceFill([
                'generated_at' => now(),
                'generated_by' => $generatedBy,
            ])->save();

            return $sheet->fresh();
        });
    }
}
