<?php

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Services\TimekeepingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AttendanceRefreshCommand extends Command
{
    protected $signature = 'attendance:refresh 
                            {--days=7 : Số ngày tính từ hôm nay trở về trước}
                            {--from= : Ngày bắt đầu (YYYY-MM-DD)}
                            {--to= : Ngày kết thúc (YYYY-MM-DD)}
                            {--skip-mapping : Bỏ qua bước refresh mapping}';

    protected $description = 'Refresh mapping attendance logs và recalculate timekeeping records';

    public function handle(TimekeepingService $timekeepingService): int
    {
        $from = $this->option('from') 
            ? Carbon::parse($this->option('from')) 
            : now()->subDays((int) $this->option('days'));
        
        $to = $this->option('to') 
            ? Carbon::parse($this->option('to')) 
            : now();

        $this->info("📅 Range: {$from->toDateString()} → {$to->toDateString()}");

        // Step 1: Refresh mapping
        if (!$this->option('skip-mapping')) {
            $this->info('🔄 Step 1: Refreshing employee mapping...');
            
            $mapping = Employee::whereNotNull('attendance_code')
                ->pluck('id', 'attendance_code')
                ->toArray();

            $this->info('   Found ' . count($mapping) . ' employees with attendance_code');

            $updated = 0;
            AttendanceLog::whereNull('employee_id')
                ->whereIn('device_user_id', array_keys($mapping))
                ->chunkById(1000, function ($logs) use ($mapping, &$updated) {
                    foreach ($logs as $log) {
                        if (isset($mapping[$log->device_user_id])) {
                            $log->update(['employee_id' => $mapping[$log->device_user_id]]);
                            $updated++;
                        }
                    }
                });

            $this->info("   ✅ Updated {$updated} logs with employee_id");
        }

        // Step 2: Recalculate timekeeping
        $this->info('🔄 Step 2: Recalculating timekeeping records...');
        
        $result = $timekeepingService->recalculateForRange($from, $to);
        
        $this->info("   ✅ Created: {$result['created']}, Updated: {$result['updated']}");

        $this->newLine();
        $this->info('🎉 Done!');

        return Command::SUCCESS;
    }
}
