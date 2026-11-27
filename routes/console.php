<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('farmwell:send-daily-reminders')
    ->dailyAt('07:00')
    ->withoutOverlapping();

Schedule::command('ai:sync-model-prices')
    ->dailyAt('02:00')
    ->withoutOverlapping();

Artisan::command('ops:failed-job-watermark', function (): void {
    $latest = DB::connection(config('queue.failed.database'))
        ->table(config('queue.failed.table'))
        ->max('id');

    $this->line((string) ($latest ?? 0));
})->purpose('Print the latest terminal queue failure ID for monitoring');
