<?php

use App\Services\LoanStatusRefreshService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('lms:refresh-loan-statuses', function (LoanStatusRefreshService $service): int {
    $updated = $service->refresh();
    $this->info("Refreshed {$updated} overdue schedule and loan status record(s).");

    return self::SUCCESS;
})->purpose('Recalculate installment overdue and configured default statuses');

Schedule::command('lms:refresh-loan-statuses')->dailyAt('00:05');
