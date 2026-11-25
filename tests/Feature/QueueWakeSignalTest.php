<?php

use App\Http\Middleware\SignalQueuedWork;
use App\Jobs\GenerateAiSummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

test('database jobs signal the worker only after they commit', function () {
    Route::post('/test-queued-work', function () {
        DB::transaction(function () {
            GenerateAiSummary::dispatch(1)->onConnection('database')->beforeCommit();
        });

        return response()->noContent();
    });
    Route::post('/test-rolled-back-work', function () {
        DB::beginTransaction();
        GenerateAiSummary::dispatch(2)->onConnection('database')->beforeCommit();
        DB::rollBack();

        return response()->noContent();
    });

    $this->post('/test-queued-work')->assertNoContent()->assertHeader(SignalQueuedWork::Header, '1');
    $this->post('/test-rolled-back-work', [], [SignalQueuedWork::Header => '1'])
        ->assertNoContent()->assertHeaderMissing(SignalQueuedWork::Header);
    $this->assertDatabaseCount('jobs', 1);
});
