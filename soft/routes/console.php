<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\PayrollEngineService;
use Carbon\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('payroll:generate-sheet {start} {end} {--no-recalc : Do not recalculate timekeeping records before generating}', function (PayrollEngineService $engine) {
    $start = Carbon::parse($this->argument('start'));
    $end = Carbon::parse($this->argument('end'));
    $recalc = !$this->option('no-recalc');

    $sheet = $engine->generateSheet($start, $end, $recalc, null);

    $this->info('Generated payroll sheet #' . $sheet->id . ' for ' . $sheet->period_start->toDateString() . ' -> ' . $sheet->period_end->toDateString());
})->purpose('Generate payroll sheet (auto) from timekeeping + commissions');
