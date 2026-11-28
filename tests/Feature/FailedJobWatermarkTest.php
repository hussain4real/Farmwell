<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('the failed-job monitor prints the latest ID without exposing job details', function () {
    $this->artisan('ops:failed-job-watermark')->expectsOutput('0')->assertSuccessful();

    foreach ([2, 9] as $id) {
        DB::table('failed_jobs')->insert([
            'id' => $id,
            'uuid' => Str::uuid()->toString(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Synthetic exception details',
        ]);
    }

    $this->artisan('ops:failed-job-watermark')
        ->expectsOutput('9')
        ->doesntExpectOutputToContain('Synthetic exception details')
        ->assertSuccessful();
});
