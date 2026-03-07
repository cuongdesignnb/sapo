<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\EmployeeWorkSchedule;
use App\Models\Holiday;
use App\Models\Shift;
use App\Models\TimekeepingRecord;
use App\Models\TimekeepingSetting;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class TimekeepingService
{
    /**
     * Recalculate timekeeping records for schedules in range.
     *
     * This is intentionally best-effort and idempotent: re-running should only
     * update the derived fields and not duplicate records.
     */
    public function recalculateForRange(Carbon $from, Carbon $to, ?int $employeeId = null): array
    {
        $fromDate = $from->copy()->startOfDay();
        $toDate = $to->copy()->endOfDay();

        $holidayMap = Holiday::query()
            ->whereBetween('holiday_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->where('status', 'active')
            ->get()
            ->keyBy(fn ($h) => $h->holiday_date->toDateString());

        $scheduleQuery = EmployeeWorkSchedule::query()
            ->whereBetween('work_date', [$fromDate->toDateString(), $toDate->toDateString()]);

        if ($employeeId) {
            $scheduleQuery->where('employee_id', $employeeId);
        }

        $schedules = $scheduleQuery->orderBy('work_date')->orderBy('slot')->get();

        $updated = 0;
        $created = 0;

        $shiftIds = $schedules->pluck('shift_id')->filter()->unique()->values()->all();
        $shifts = $shiftIds
            ? Shift::query()->whereIn('id', $shiftIds)->get()->keyBy('id')
            : collect();

        $warehouseIds = $schedules->pluck('warehouse_id')->filter()->unique()->values()->all();
        $settingsByWarehouse = TimekeepingSetting::query()
            ->where('status', 'active')
            ->where(function ($q) use ($warehouseIds) {
                $q->whereNull('warehouse_id');
                if (!empty($warehouseIds)) {
                    $q->orWhereIn('warehouse_id', $warehouseIds);
                }
            })
            ->get()
            ->keyBy(fn ($s) => $s->warehouse_id === null ? 'global' : (string) $s->warehouse_id);

        $globalSetting = $settingsByWarehouse->get('global');

        foreach ($schedules as $schedule) {
            $existing = TimekeepingRecord::query()->where('employee_work_schedule_id', $schedule->id)->first();
            if ($existing && (bool) $existing->manual_override) {
                // Respect manual edits from the UI; do not overwrite.
                continue;
            }

            $shift = $schedule->shift_id ? $shifts->get($schedule->shift_id) : null;

            $setting = $settingsByWarehouse->get((string) $schedule->warehouse_id) ?? $globalSetting;
            $useShiftAllowances = (bool) ($setting?->use_shift_allowances ?? true);
            $lateGrace = (int) ($setting?->late_grace_minutes ?? 0);
            $earlyGrace = (int) ($setting?->early_grace_minutes ?? 0);
            $allowMultipleShiftsOneInOut = (bool) ($setting?->allow_multiple_shifts_one_inout ?? false);
            $enforceShiftCheckinWindow = (bool) ($setting?->enforce_shift_checkin_window ?? false);
            $otRounding = (int) ($setting?->ot_rounding_minutes ?? 0);
            $otAfter = (int) ($setting?->ot_after_minutes ?? 0);

            $scheduleStart = $this->buildScheduleDateTime($schedule->work_date, $schedule->start_time, $shift?->start_time);
            $scheduleEnd = $this->buildScheduleDateTime($schedule->work_date, $schedule->end_time, $shift?->end_time);

            if ($scheduleStart && $scheduleEnd && $scheduleEnd->lessThanOrEqualTo($scheduleStart)) {
                $scheduleEnd = $scheduleEnd->copy()->addDay();
            }

            // find punch logs in a generous window around schedule
            $windowStart = ($scheduleStart ?? Carbon::parse($schedule->work_date))->copy()->subHours(8);
            $windowEnd = ($scheduleEnd ?? Carbon::parse($schedule->work_date)->endOfDay())->copy()->addHours(8);

            $logs = AttendanceLog::query()
                ->where('employee_id', $schedule->employee_id)
                ->whereBetween('punched_at', [$windowStart, $windowEnd])
                ->orderBy('punched_at')
                ->get();

            $checkIn = null;
            $checkOut = null;

            // Determine check-in / check-out, optionally enforcing each shift's check-in window.
            if (
                $enforceShiftCheckinWindow
                && $shift
                && $shift->checkin_start_time
                && $shift->checkin_end_time
                && $logs->isNotEmpty()
            ) {
                $checkinWindowStart = $this->buildScheduleDateTime($schedule->work_date, $shift->checkin_start_time, null);
                $checkinWindowEnd = $this->buildScheduleDateTime($schedule->work_date, $shift->checkin_end_time, null);

                if ($checkinWindowStart && $checkinWindowEnd && $checkinWindowEnd->lessThanOrEqualTo($checkinWindowStart)) {
                    $checkinWindowEnd = $checkinWindowEnd->copy()->addDay();
                }

                if ($checkinWindowStart && $checkinWindowEnd) {
                    $first = $logs->first(function ($l) use ($checkinWindowStart, $checkinWindowEnd) {
                        $p = $l->punched_at instanceof Carbon ? $l->punched_at : Carbon::parse($l->punched_at);
                        return $p->greaterThanOrEqualTo($checkinWindowStart) && $p->lessThanOrEqualTo($checkinWindowEnd);
                    });

                    if ($first) {
                        $checkIn = $first->punched_at;

                        // For checkout, keep using the broader schedule window, but only after check-in.
                        // Only set checkout if it's a DIFFERENT log than check-in (not same punch)
                        $last = $logs->last(function ($l) use ($checkIn, $first) {
                            if ($l->id === $first->id) {
                                return false; // Skip same log as check-in
                            }
                            $p = $l->punched_at instanceof Carbon ? $l->punched_at : Carbon::parse($l->punched_at);
                            $in = $checkIn instanceof Carbon ? $checkIn : Carbon::parse($checkIn);
                            return $p->greaterThan($in);
                        });
                        $checkOut = $last?->punched_at;
                    }
                }
            }

            if (!$checkIn && $logs->isNotEmpty() && ($allowMultipleShiftsOneInOut || !$enforceShiftCheckinWindow)) {
                $checkIn = $logs->first()?->punched_at;
                // Only set checkout if there are multiple logs and last is different from first
                if ($logs->count() > 1) {
                    $lastLog = $logs->last();
                    $firstLog = $logs->first();
                    // Ensure checkout is a different log and later than check-in
                    if ($lastLog->id !== $firstLog->id) {
                        $checkOut = $lastLog->punched_at;
                    }
                }
            }

            $allowLate = $useShiftAllowances ? (int) ($shift?->allow_late_minutes ?? $lateGrace) : $lateGrace;
            $allowEarly = $useShiftAllowances ? (int) ($shift?->allow_early_minutes ?? $earlyGrace) : $earlyGrace;

            $lateMinutes = 0;
            $earlyMinutes = 0;
            $otMinutes = 0;
            $workedMinutes = 0;

            if ($checkIn && $checkOut) {
                $workedMinutes = max(0, Carbon::parse($checkOut)->diffInMinutes(Carbon::parse($checkIn), false));
            }

            if ($scheduleStart && $checkIn) {
                $diff = Carbon::parse($checkIn)->diffInMinutes($scheduleStart, false);
                $lateMinutes = max(0, $diff - $allowLate);
            }

            if ($scheduleEnd && $checkOut) {
                $checkOutCarbon = Carbon::parse($checkOut);
                
                // Về sớm: chỉ tính nếu checkOut < scheduleEnd
                if ($checkOutCarbon->lessThan($scheduleEnd)) {
                    $diffEarly = $scheduleEnd->diffInMinutes($checkOutCarbon);
                    $earlyMinutes = max(0, $diffEarly - $allowEarly);
                } else {
                    $earlyMinutes = 0;
                }

                // OT: chỉ tính nếu checkOut > scheduleEnd
                if ($checkOutCarbon->greaterThan($scheduleEnd)) {
                    $rawOt = $checkOutCarbon->diffInMinutes($scheduleEnd);
                    $rawOt = max(0, $rawOt - $otAfter);
                    if ($otRounding > 0) {
                        $rawOt = (int) (floor($rawOt / $otRounding) * $otRounding);
                    }
                    $otMinutes = $rawOt;
                } else {
                    $otMinutes = 0;
                }
            }

            $holiday = $holidayMap->get(Carbon::parse($schedule->work_date)->toDateString());

            $attributes = [
                'employee_id' => $schedule->employee_id,
                'employee_work_schedule_id' => $schedule->id,
                'warehouse_id' => $schedule->warehouse_id,
                'shift_id' => $schedule->shift_id,
                'work_date' => $schedule->work_date,
                'slot' => $schedule->slot ?? 1,
                'scheduled_start_at' => $scheduleStart,
                'scheduled_end_at' => $scheduleEnd,
                'check_in_at' => $checkIn,
                'check_out_at' => $checkOut,
                'source' => $logs->isNotEmpty() ? 'device' : 'none',
                'attendance_type' => 'work',
                'manual_override' => false,
                'late_minutes' => $lateMinutes,
                'early_minutes' => $earlyMinutes,
                'ot_minutes' => $otMinutes,
                'worked_minutes' => $workedMinutes,
                'is_holiday' => (bool) $holiday,
                'holiday_multiplier' => $holiday ? (float) $holiday->multiplier : 1,
                'raw' => [
                    'log_ids' => $logs->pluck('id')->values()->all(),
                    'device_ids' => $logs->pluck('attendance_device_id')->unique()->values()->all(),
                ],
            ];

            if ($existing) {
                $existing->fill($attributes)->save();
                $updated++;
            } else {
                TimekeepingRecord::create($attributes);
                $created++;
            }
        }

        return compact('created', 'updated');
    }

    private function buildScheduleDateTime($workDate, $scheduleTime, $fallbackShiftTime): ?Carbon
    {
        $time = $scheduleTime ?? $fallbackShiftTime;
        if (!$time) {
            return null;
        }

        $timeStr = is_string($time) ? $time : (string) $time;

        // $workDate may be a Carbon (casted date) or a string. Avoid concatenating
        // a datetime string (e.g. "2026-01-05 00:00:00") with another time.
        if ($workDate instanceof Carbon) {
            $date = $workDate->copy();
        } else {
            $workDateStr = is_string($workDate) ? trim($workDate) : (string) $workDate;
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $workDateStr)) {
                $date = Carbon::createFromFormat('d/m/Y', $workDateStr);
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $workDateStr)) {
                $date = Carbon::parse(substr($workDateStr, 0, 10));
            } else {
                $date = Carbon::parse($workDateStr);
            }
        }

        return $date->copy()->startOfDay()->setTimeFromTimeString($timeStr);
    }
}
