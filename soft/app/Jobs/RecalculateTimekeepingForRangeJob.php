<?php

namespace App\Jobs;

use App\Services\TimekeepingService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalculateTimekeepingForRangeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param string $from ISO/date string
     * @param string $to ISO/date string
     * @param array<int> $employeeIds
     */
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly array $employeeIds = [],
    ) {
    }

    public function handle(TimekeepingService $timekeepingService): void
    {
        $from = Carbon::parse($this->from);
        $to = Carbon::parse($this->to);

        $employeeIds = array_values(array_unique(array_map('intval', $this->employeeIds)));
        $employeeIds = array_values(array_filter($employeeIds, fn ($id) => $id > 0));

        if (empty($employeeIds)) {
            // No employees impacted => nothing to do.
            return;
        }

        foreach ($employeeIds as $employeeId) {
            $timekeepingService->recalculateForRange($from, $to, $employeeId);
        }
    }
}
