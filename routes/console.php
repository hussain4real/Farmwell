<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ops:failed-job-watermark', function (): void {
    $latest = DB::connection(config('queue.failed.database'))
        ->table(config('queue.failed.table'))
        ->max('id');

    $this->line((string) ($latest ?? 0));
})->purpose('Print the latest terminal queue failure ID for monitoring');
